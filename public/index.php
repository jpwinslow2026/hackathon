<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Database;
use App\OpenAIService;
use App\QuestionCatalog;

$db = Database::connection();
$action = (string) ($_GET['action'] ?? 'home');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if ($action === 'create') {
            $workloads = array_values(array_intersect(array_keys(QuestionCatalog::workloads()), (array) ($_POST['workloads'] ?? [])));
            if (trim((string) ($_POST['customer_name'] ?? '')) === '' || $workloads === []) {
                throw new RuntimeException('Customer name and at least one workload are required.');
            }
            $stmt = $db->prepare('INSERT INTO assessments (customer_name, opportunity_name, consultant_name, selected_workloads) VALUES (?, ?, ?, ?)');
            $stmt->execute([
                trim((string) $_POST['customer_name']),
                trim((string) ($_POST['opportunity_name'] ?? '')),
                trim((string) ($_POST['consultant_name'] ?? '')),
                json_encode($workloads, JSON_THROW_ON_ERROR),
            ]);
            redirect('?action=questions&id=' . $db->lastInsertId());
        }

        if ($action === 'save' && $id > 0) {
            $assessment = findAssessment($db, $id);
            foreach (QuestionCatalog::questions($assessment['selected_workloads']) as $question) {
                $value = trim((string) ($_POST['answers'][$question['id']] ?? ''));
                $stmt = $db->prepare(
                    'INSERT INTO assessment_answers (assessment_id, question_id, answer_text) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE answer_text = VALUES(answer_text), updated_at = CURRENT_TIMESTAMP'
                );
                $stmt->execute([$id, $question['id'], $value]);
            }
            redirect('?action=review&id=' . $id);
        }

        if ($action === 'analyze' && $id > 0) {
            $assessment = findAssessment($db, $id);
            $answers = findAnswers($db, $id);
            $analysis = (new OpenAIService())->analyze($assessment, $answers);
            $db->prepare('DELETE FROM assessment_findings WHERE assessment_id = ?')->execute([$id]);
            $map = [
                'facts' => 'fact', 'risks' => 'risk', 'assumptions' => 'assumption',
                'dependencies' => 'dependency', 'exclusions' => 'exclusion',
                'open_questions' => 'open_question',
            ];
            $insert = $db->prepare('INSERT INTO assessment_findings (assessment_id, finding_type, finding_text) VALUES (?, ?, ?)');
            foreach ($map as $key => $type) {
                foreach ($analysis[$key] ?? [] as $text) {
                    if (trim((string) $text) !== '') {
                        $insert->execute([$id, $type, trim((string) $text)]);
                    }
                }
            }
            $_SESSION['next_question_' . $id] = $analysis['next_question'] ?? '';
            redirect('?action=review&id=' . $id);
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

if ($action === 'export' && $id > 0) {
    $assessment = findAssessment($db, $id);
    $answers = findAnswers($db, $id);
    $findings = findFindings($db, $id);
    header('Content-Type: application/msword; charset=UTF-8');
    header('Content-Disposition: attachment; filename="migration-scope-' . $id . '.doc"');
    echo exportDocument($assessment, $answers, $findings);
    exit;
}

renderHeader('Migration Scoping');
if ($error !== null) {
    echo '<div class="alert">' . e($error) . '</div>';
}

if ($action === 'home') {
    $items = $db->query('SELECT * FROM assessments ORDER BY updated_at DESC')->fetchAll();
    echo '<section class="hero"><div><p class="eyebrow">Microsoft Professional Services</p><h1>Migration Scoping Workspace</h1><p>Turn guided discovery into a reviewable, SOW-ready scope.</p></div><a class="button" href="?action=new">New assessment</a></section>';
    echo '<section class="panel"><h2>Assessments</h2>';
    if ($items === []) {
        echo '<p class="muted">No assessments yet. Start with a customer and select the migration workloads.</p>';
    } else {
        echo '<div class="cards">';
        foreach ($items as $item) {
            echo '<a class="card" href="?action=questions&id=' . (int) $item['id'] . '"><strong>' . e($item['customer_name']) . '</strong><span>' . e($item['opportunity_name']) . '</span><small>Updated ' . e($item['updated_at']) . '</small></a>';
        }
        echo '</div>';
    }
    echo '</section>';
} elseif ($action === 'new') {
    echo '<section class="panel narrow"><p class="eyebrow">New engagement</p><h1>Create assessment</h1><form method="post" action="?action=create">';
    echo csrf_field();
    field('customer_name', 'Customer name', 'text', '', true);
    field('opportunity_name', 'Opportunity or project name');
    field('consultant_name', 'Consultant name');
    echo '<fieldset><legend>Workloads in scope</legend><div class="checks">';
    foreach (QuestionCatalog::workloads() as $key => $label) {
        echo '<label><input type="checkbox" name="workloads[]" value="' . e($key) . '"> ' . e($label) . '</label>';
    }
    echo '</div></fieldset><button class="button" type="submit">Start discovery</button></form></section>';
} elseif ($action === 'questions' && $id > 0) {
    $assessment = findAssessment($db, $id);
    $answers = answerMap(findAnswers($db, $id));
    $questions = QuestionCatalog::questions($assessment['selected_workloads']);
    echo assessmentNav($assessment, 'questions');
    echo '<section class="panel"><p class="eyebrow">Guided discovery</p><h1>' . e($assessment['customer_name']) . '</h1><p class="muted">' . count($questions) . ' questions based on the selected workloads.</p>';
    echo '<form method="post" action="?action=save&id=' . $id . '">' . csrf_field();
    foreach ($questions as $index => $question) {
        echo '<div class="question"><span>' . ($index + 1) . '</span><label for="' . e($question['id']) . '">' . e($question['label']) . '</label>';
        $value = $answers[$question['id']] ?? '';
        if ($question['type'] === 'textarea') {
            echo '<textarea id="' . e($question['id']) . '" name="answers[' . e($question['id']) . ']" rows="4">' . e($value) . '</textarea>';
        } elseif ($question['type'] === 'select') {
            echo '<select id="' . e($question['id']) . '" name="answers[' . e($question['id']) . ']"><option value="">Select…</option>';
            foreach ($question['options'] as $option) {
                echo '<option' . ($value === $option ? ' selected' : '') . '>' . e($option) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<input id="' . e($question['id']) . '" name="answers[' . e($question['id']) . ']" value="' . e($value) . '">';
        }
        echo '</div>';
    }
    echo '<button class="button" type="submit">Save and review</button></form></section>';
} elseif ($action === 'review' && $id > 0) {
    $assessment = findAssessment($db, $id);
    $answers = findAnswers($db, $id);
    $findings = findFindings($db, $id);
    echo assessmentNav($assessment, 'review');
    echo '<section class="hero compact"><div><p class="eyebrow">Consultant review</p><h1>' . e($assessment['customer_name']) . '</h1><p>Confirm collected facts before using this material in a statement of work.</p></div><a class="button secondary" href="?action=export&id=' . $id . '">Download Word document</a></section>';
    $next = (string) ($_SESSION['next_question_' . $id] ?? '');
    if ($next !== '') {
        echo '<section class="followup"><strong>Recommended follow-up</strong><p>' . e($next) . '</p></section>';
    }
    echo '<section class="panel"><div class="section-heading"><h2>Discovery answers</h2><a href="?action=questions&id=' . $id . '">Edit answers</a></div><dl>';
    $labels = [];
    foreach (QuestionCatalog::questions($assessment['selected_workloads']) as $question) {
        $labels[$question['id']] = $question['label'];
    }
    foreach ($answers as $answer) {
        echo '<dt>' . e($labels[$answer['question_id']] ?? $answer['question_id']) . '</dt><dd>' . nl2br(e($answer['answer_text'] ?: 'Not answered')) . '</dd>';
    }
    echo '</dl></section>';
    echo '<section class="panel"><div class="section-heading"><div><h2>AI-assisted findings</h2><p class="muted">Generated content is unapproved until reviewed by a consultant.</p></div><form method="post" action="?action=analyze&id=' . $id . '">' . csrf_field() . '<button class="button" type="submit">Analyze scope</button></form></div>';
    if ($findings === []) {
        echo '<p class="muted">No findings generated yet.</p>';
    } else {
        foreach (groupFindings($findings) as $type => $rows) {
            echo '<h3>' . e(ucwords(str_replace('_', ' ', $type))) . '</h3><ul>';
            foreach ($rows as $row) echo '<li>' . e($row['finding_text']) . '</li>';
            echo '</ul>';
        }
    }
    echo '</section>';
} else {
    http_response_code(404);
    echo '<section class="panel"><h1>Assessment not found</h1><a href="./">Return home</a></section>';
}
renderFooter();

function findAssessment(PDO $db, int $id): array
{
    $stmt = $db->prepare('SELECT * FROM assessments WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException('Assessment not found.');
    $row['selected_workloads'] = json_decode($row['selected_workloads'], true, 512, JSON_THROW_ON_ERROR);
    return $row;
}
function findAnswers(PDO $db, int $id): array
{
    $stmt = $db->prepare('SELECT * FROM assessment_answers WHERE assessment_id = ? ORDER BY id');
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}
function findFindings(PDO $db, int $id): array
{
    $stmt = $db->prepare('SELECT * FROM assessment_findings WHERE assessment_id = ? ORDER BY finding_type, id');
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}
function answerMap(array $answers): array
{
    $map = [];
    foreach ($answers as $answer) $map[$answer['question_id']] = $answer['answer_text'];
    return $map;
}
function groupFindings(array $findings): array
{
    $groups = [];
    foreach ($findings as $finding) $groups[$finding['finding_type']][] = $finding;
    return $groups;
}
function field(string $name, string $label, string $type = 'text', string $value = '', bool $required = false): void
{
    echo '<label for="' . e($name) . '">' . e($label) . '</label><input id="' . e($name) . '" name="' . e($name) . '" type="' . e($type) . '" value="' . e($value) . '"' . ($required ? ' required' : '') . '>';
}
function assessmentNav(array $assessment, string $active): string
{
    $id = (int) $assessment['id'];
    return '<nav class="steps"><a class="' . ($active === 'questions' ? 'active' : '') . '" href="?action=questions&id=' . $id . '">1. Discovery</a><a class="' . ($active === 'review' ? 'active' : '') . '" href="?action=review&id=' . $id . '">2. Review</a></nav>';
}
function renderHeader(string $title): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title><link rel="stylesheet" href="assets/app.css"></head><body><header class="topbar"><a href="./"><span class="mark">M</span><strong>Migration Scope</strong></a></header><main>';
}
function renderFooter(): void
{
    echo '</main><footer>Consultant-reviewed Microsoft migration discovery</footer></body></html>';
}
function exportDocument(array $assessment, array $answers, array $findings): string
{
    $labels = [];
    foreach (QuestionCatalog::questions($assessment['selected_workloads']) as $q) $labels[$q['id']] = $q['label'];
    $html = '<html><head><meta charset="UTF-8"><style>body{font-family:Arial;color:#172033}h1{color:#0f4c81}h2{border-bottom:1px solid #bbb;padding-bottom:6px}dt{font-weight:bold;margin-top:12px}dd{margin:4px 0 12px}</style></head><body>';
    $html .= '<h1>Microsoft Migration Scoping Summary</h1><p><strong>Customer:</strong> ' . e($assessment['customer_name']) . '</p><p><strong>Opportunity:</strong> ' . e($assessment['opportunity_name']) . '</p><p><strong>Consultant:</strong> ' . e($assessment['consultant_name']) . '</p>';
    $html .= '<h2>Discovery Responses</h2><dl>';
    foreach ($answers as $a) $html .= '<dt>' . e($labels[$a['question_id']] ?? $a['question_id']) . '</dt><dd>' . nl2br(e($a['answer_text'] ?: 'Not answered')) . '</dd>';
    $html .= '</dl>';
    foreach (groupFindings($findings) as $type => $rows) {
        $html .= '<h2>' . e(ucwords(str_replace('_', ' ', $type))) . '</h2><ul>';
        foreach ($rows as $row) $html .= '<li>' . e($row['finding_text']) . '</li>';
        $html .= '</ul>';
    }
    return $html . '<p><em>Generated findings require consultant validation before contractual use.</em></p></body></html>';
}
