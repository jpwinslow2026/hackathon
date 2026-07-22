<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth;
use App\Database;
use App\OpenAIService;
use App\QuestionCatalog;

$db = Database::connection();
$action = (string) ($_GET['action'] ?? 'home');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$error = null;

if ($action === 'logout') {
    Auth::logout();
    redirect('?action=login');
}

if ($action === 'login') {
    if (Auth::id() > 0) {
        redirect('./');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (Auth::attempt($db, (string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            redirect('./');
        }
        $error = 'The username or password was not recognized.';
    }
    renderHeader('Sign in', false);
    if ($error) echo '<div class="alert">' . e($error) . '</div>';
    echo '<section class="panel login"><p class="eyebrow">Sales Engineering</p><h1>Sign in</h1><p class="muted">Use your locally assigned account.</p><form method="post" action="?action=login">' . csrf_field();
    field('username', 'Username', 'text', '', true, 'username');
    field('password', 'Password', 'password', '', true, 'current-password');
    echo '<button class="button" type="submit">Sign in</button></form></section>';
    renderFooter();
    exit;
}

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if ($action === 'create') {
            $customer = trim((string) ($_POST['customer_name'] ?? ''));
            if ($customer === '') {
                throw new RuntimeException('Customer name is required.');
            }
            $workloads = array_keys(QuestionCatalog::workloads());
            $stmt = $db->prepare('INSERT INTO assessments (user_id, customer_name, opportunity_name, consultant_name, selected_workloads) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                Auth::id(),
                $customer,
                trim((string) ($_POST['opportunity_name'] ?? '')),
                Auth::user()['display_name'],
                json_encode($workloads, JSON_THROW_ON_ERROR),
            ]);
            redirect('?action=questions&id=' . $db->lastInsertId());
        }

        if ($action === 'save' && $id > 0) {
            $assessment = findAssessment($db, $id);
            foreach (QuestionCatalog::questions($assessment['selected_workloads']) as $question) {
                $posted = $_POST['answers'][$question['id']] ?? '';
                $value = is_array($posted) ? implode(', ', array_map('trim', $posted)) : trim((string) $posted);
                $stmt = $db->prepare(
                    'INSERT INTO assessment_answers (assessment_id, question_id, answer_text) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE answer_text = VALUES(answer_text), updated_at = CURRENT_TIMESTAMP'
                );
                $stmt->execute([$id, $question['id'], $value]);
            }
            $db->prepare("UPDATE assessments SET status = 'review' WHERE id = ? AND user_id = ?")->execute([$id, Auth::id()]);
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
                    if (trim((string) $text) !== '') $insert->execute([$id, $type, trim((string) $text)]);
                }
            }
            $_SESSION['next_question_' . $id] = $analysis['next_question'] ?? '';
            redirect('?action=review&id=' . $id);
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

if (($action === 'export' || $action === 'export-text') && $id > 0) {
    $assessment = findAssessment($db, $id);
    $answers = findAnswers($db, $id);
    $findings = findFindings($db, $id);
    if ($action === 'export-text') {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="migration-scope-' . $id . '.txt"');
        echo exportText($assessment, $answers, $findings);
    } else {
        header('Content-Type: application/msword; charset=UTF-8');
        header('Content-Disposition: attachment; filename="migration-scope-' . $id . '.doc"');
        echo exportDocument($assessment, $answers, $findings);
    }
    exit;
}

renderHeader('Microsoft 365 Migration Scoping');
if ($error !== null) echo '<div class="alert">' . e($error) . '</div>';

if ($action === 'home') {
    $stmt = $db->prepare('SELECT * FROM assessments WHERE user_id = ? ORDER BY updated_at DESC');
    $stmt->execute([Auth::id()]);
    $items = $stmt->fetchAll();
    echo '<section class="hero"><div><p class="eyebrow">Microsoft 365 Sales Engineering</p><h1>Migration Scoping Workspace</h1><p>Guide a customer conversation and turn the answers into an SOW-ready discovery summary.</p></div><a class="button" href="?action=new">New assessment</a></section>';
    echo '<section class="panel"><h2>My assessments</h2>';
    if ($items === []) {
        echo '<p class="muted">No assessments yet. Create one to begin customer discovery.</p>';
    } else {
        echo '<div class="cards">';
        foreach ($items as $item) {
            echo '<a class="card" href="?action=questions&id=' . (int) $item['id'] . '"><strong>' . e($item['customer_name']) . '</strong><span>' . e($item['opportunity_name']) . '</span><small>' . e(ucfirst($item['status'])) . ' · Updated ' . e($item['updated_at']) . '</small></a>';
        }
        echo '</div>';
    }
    echo '</section>';
} elseif ($action === 'new') {
    echo '<section class="panel narrow"><p class="eyebrow">New customer conversation</p><h1>Create assessment</h1><form method="post" action="?action=create">' . csrf_field();
    field('customer_name', 'Customer name', 'text', '', true);
    field('opportunity_name', 'Opportunity or project name');
    echo '<p class="muted">The questionnaire begins with the current email environment, then covers the target Microsoft 365 tenant and migration workloads.</p><button class="button" type="submit">Begin Q&A</button></form></section>';
} elseif ($action === 'questions' && $id > 0) {
    $assessment = findAssessment($db, $id);
    $answers = answerMap(findAnswers($db, $id));
    $questions = QuestionCatalog::questions($assessment['selected_workloads']);
    echo assessmentNav($assessment, 'questions');
    echo '<section class="panel"><p class="eyebrow">Customer Q&A</p><h1>' . e($assessment['customer_name']) . '</h1><p class="muted">Work through these questions with the customer. Use “Not applicable” where appropriate.</p><form method="post" action="?action=save&id=' . $id . '">' . csrf_field();
    $currentSection = '';
    foreach ($questions as $index => $question) {
        if ($question['section'] !== $currentSection) {
            $currentSection = $question['section'];
            echo '<div class="question-section"><h2>' . e($currentSection) . '</h2></div>';
        }
        echo '<div class="question"><span>' . ($index + 1) . '</span><label for="' . e($question['id']) . '">' . e($question['label']) . '</label>';
        $value = $answers[$question['id']] ?? '';
        if ($question['type'] === 'textarea') {
            echo '<textarea id="' . e($question['id']) . '" name="answers[' . e($question['id']) . ']" rows="4">' . e($value) . '</textarea>';
        } elseif ($question['type'] === 'select') {
            echo '<select id="' . e($question['id']) . '" name="answers[' . e($question['id']) . ']"><option value="">Select…</option>';
            foreach ($question['options'] as $option) echo '<option' . ($value === $option ? ' selected' : '') . '>' . e($option) . '</option>';
            echo '</select>';
        } elseif ($question['type'] === 'multiselect') {
            $selected = array_map('trim', explode(',', $value));
            echo '<div class="checks">';
            foreach ($question['options'] as $option) {
                echo '<label><input type="checkbox" name="answers[' . e($question['id']) . '][]" value="' . e($option) . '"' . (in_array($option, $selected, true) ? ' checked' : '') . '> ' . e($option) . '</label>';
            }
            echo '</div>';
        } else {
            echo '<input id="' . e($question['id']) . '" type="' . e($question['type']) . '" name="answers[' . e($question['id']) . ']" value="' . e($value) . '">';
        }
        echo '</div>';
    }
    echo '<button class="button" type="submit">Save and build summary</button></form></section>';
} elseif ($action === 'review' && $id > 0) {
    $assessment = findAssessment($db, $id);
    $answers = findAnswers($db, $id);
    $findings = findFindings($db, $id);
    echo assessmentNav($assessment, 'review');
    echo '<section class="hero compact"><div><p class="eyebrow">Scope output</p><h1>' . e($assessment['customer_name']) . '</h1><p>This page can be copied directly, or exported as a basic Word or text file.</p></div><div class="actions"><a class="button secondary" href="?action=export&id=' . $id . '">Word</a><a class="button secondary" href="?action=export-text&id=' . $id . '">Text</a></div></section>';
    $next = (string) ($_SESSION['next_question_' . $id] ?? '');
    if ($next !== '') echo '<section class="followup"><strong>Recommended follow-up</strong><p>' . e($next) . '</p></section>';
    echo '<section class="panel copy-output"><div class="section-heading"><div><p class="eyebrow">Discovery summary</p><h2>Customer requirements</h2></div><a href="?action=questions&id=' . $id . '">Edit answers</a></div>';
    renderAnswers($assessment, $answers);
    echo '</section><section class="panel"><div class="section-heading"><div><h2>AI-assisted SOW findings</h2><p class="muted">These are proposals and require Sales Engineer review.</p></div><form method="post" action="?action=analyze&id=' . $id . '">' . csrf_field() . '<button class="button" type="submit">Analyze scope</button></form></div>';
    if ($findings === []) {
        echo '<p class="muted">No findings generated yet. The Q&A output is available without AI.</p>';
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
    $stmt = $db->prepare('SELECT * FROM assessments WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, Auth::id()]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException('Assessment not found or access denied.');
    $row['selected_workloads'] = json_decode($row['selected_workloads'], true, 512, JSON_THROW_ON_ERROR);
    return $row;
}
function findAnswers(PDO $db, int $id): array
{
    $stmt = $db->prepare('SELECT aa.* FROM assessment_answers aa JOIN assessments a ON a.id = aa.assessment_id WHERE aa.assessment_id = ? AND a.user_id = ? ORDER BY aa.id');
    $stmt->execute([$id, Auth::id()]);
    return $stmt->fetchAll();
}
function findFindings(PDO $db, int $id): array
{
    $stmt = $db->prepare('SELECT af.* FROM assessment_findings af JOIN assessments a ON a.id = af.assessment_id WHERE af.assessment_id = ? AND a.user_id = ? ORDER BY af.finding_type, af.id');
    $stmt->execute([$id, Auth::id()]);
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
function field(string $name, string $label, string $type = 'text', string $value = '', bool $required = false, string $autocomplete = ''): void
{
    echo '<label for="' . e($name) . '">' . e($label) . '</label><input id="' . e($name) . '" name="' . e($name) . '" type="' . e($type) . '" value="' . e($value) . '"' . ($required ? ' required' : '') . ($autocomplete ? ' autocomplete="' . e($autocomplete) . '"' : '') . '>';
}
function assessmentNav(array $assessment, string $active): string
{
    $id = (int) $assessment['id'];
    return '<nav class="steps"><a class="' . ($active === 'questions' ? 'active' : '') . '" href="?action=questions&id=' . $id . '">1. Customer Q&A</a><a class="' . ($active === 'review' ? 'active' : '') . '" href="?action=review&id=' . $id . '">2. Scope output</a></nav>';
}
function renderHeader(string $title, bool $showUser = true): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title><link rel="stylesheet" href="assets/app.css"></head><body><header class="topbar"><a href="./"><span class="mark">M</span><strong>M365 Migration Scope</strong></a>';
    if ($showUser && Auth::user()) echo '<div class="user-menu"><span>' . e(Auth::user()['display_name']) . '</span><a href="?action=logout">Sign out</a></div>';
    echo '</header><main>';
}
function renderFooter(): void
{
    echo '</main><footer>Sales Engineer-reviewed Microsoft 365 migration discovery</footer></body></html>';
}
function labelsById(array $assessment): array
{
    $labels = [];
    foreach (QuestionCatalog::questions($assessment['selected_workloads']) as $q) $labels[$q['id']] = ['label' => $q['label'], 'section' => $q['section']];
    return $labels;
}
function renderAnswers(array $assessment, array $answers): void
{
    $labels = labelsById($assessment);
    $section = '';
    echo '<dl>';
    foreach ($answers as $answer) {
        $meta = $labels[$answer['question_id']] ?? ['label' => $answer['question_id'], 'section' => 'Other'];
        if ($meta['section'] !== $section) {
            $section = $meta['section'];
            echo '</dl><h3 class="output-section">' . e($section) . '</h3><dl>';
        }
        echo '<dt>' . e($meta['label']) . '</dt><dd>' . nl2br(e($answer['answer_text'] ?: 'Not answered')) . '</dd>';
    }
    echo '</dl>';
}
function exportText(array $assessment, array $answers, array $findings): string
{
    $labels = labelsById($assessment);
    $out = "MICROSOFT 365 MIGRATION SCOPING SUMMARY\n";
    $out .= "Customer: " . $assessment['customer_name'] . "\nOpportunity: " . $assessment['opportunity_name'] . "\nConsultant: " . $assessment['consultant_name'] . "\n\n";
    $section = '';
    foreach ($answers as $answer) {
        $meta = $labels[$answer['question_id']] ?? ['label' => $answer['question_id'], 'section' => 'Other'];
        if ($meta['section'] !== $section) {
            $section = $meta['section'];
            $out .= strtoupper($section) . "\n" . str_repeat('-', strlen($section)) . "\n";
        }
        $out .= $meta['label'] . "\n" . ($answer['answer_text'] ?: 'Not answered') . "\n\n";
    }
    foreach (groupFindings($findings) as $type => $rows) {
        $out .= strtoupper(str_replace('_', ' ', $type)) . "\n";
        foreach ($rows as $row) $out .= "- " . $row['finding_text'] . "\n";
        $out .= "\n";
    }
    return $out . "Generated findings require Sales Engineer validation before contractual use.\n";
}
function exportDocument(array $assessment, array $answers, array $findings): string
{
    $text = exportText($assessment, $answers, $findings);
    return '<html><head><meta charset="UTF-8"><style>body{font-family:Arial;color:#172033;line-height:1.45;white-space:pre-wrap}h1{color:#0f4c81}</style></head><body><h1>Microsoft 365 Migration Scoping Summary</h1>' . e($text) . '</body></html>';
}
