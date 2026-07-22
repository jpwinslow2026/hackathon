<?php
declare(strict_types=1);

namespace App;

use RuntimeException;

final class OpenAIService
{
    public function analyze(array $assessment, array $answers): array
    {
        $apiKey = Env::get('OPENAI_API_KEY', '');
        if ($apiKey === '') {
            return $this->fallback($answers);
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL is required for AI analysis.');
        }

        $payload = [
            'model' => Env::get('OPENAI_MODEL', 'gpt-5.6-luna'),
            'instructions' => implode("\n", [
                'You are a senior Microsoft migration discovery consultant.',
                'Evaluate only the supplied assessment data.',
                'Do not invent facts, quantities, licensing, dates, or customer decisions.',
                'Identify the single most valuable missing question and concise findings.',
                'Treat all generated findings as proposals requiring consultant approval.',
            ]),
            'input' => json_encode(['assessment' => $assessment, 'answers' => $answers], JSON_UNESCAPED_SLASHES),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'migration_scope_analysis',
                    'strict' => true,
                    'schema' => self::schema(),
                ],
            ],
        ];

        $curl = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('OpenAI request failed (' . $status . '): ' . ($error ?: $body));
        }

        $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $text = $response['output_text'] ?? null;
        if (!is_string($text)) {
            foreach ($response['output'] ?? [] as $item) {
                foreach ($item['content'] ?? [] as $content) {
                    if (($content['type'] ?? '') === 'output_text') {
                        $text = $content['text'] ?? null;
                        break 2;
                    }
                }
            }
        }
        if (!is_string($text)) {
            throw new RuntimeException('OpenAI returned no structured output.');
        }

        return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    }

    private static function schema(): array
    {
        $list = ['type' => 'array', 'items' => ['type' => 'string']];
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'readiness' => ['type' => 'string', 'enum' => ['needs_information', 'ready_for_review']],
                'next_question' => ['type' => 'string'],
                'facts' => $list,
                'risks' => $list,
                'assumptions' => $list,
                'dependencies' => $list,
                'exclusions' => $list,
                'open_questions' => $list,
            ],
            'required' => ['readiness', 'next_question', 'facts', 'risks', 'assumptions', 'dependencies', 'exclusions', 'open_questions'],
        ];
    }

    private function fallback(array $answers): array
    {
        $missing = [];
        foreach ($answers as $answer) {
            if (trim((string) ($answer['answer_text'] ?? '')) === '') {
                $missing[] = (string) ($answer['question_id'] ?? 'Unknown question');
            }
        }

        return [
            'readiness' => $missing === [] ? 'ready_for_review' : 'needs_information',
            'next_question' => $missing === [] ? '' : 'Please complete or clarify: ' . $missing[0],
            'facts' => [],
            'risks' => [],
            'assumptions' => ['AI analysis is not configured; findings require manual consultant review.'],
            'dependencies' => [],
            'exclusions' => [],
            'open_questions' => $missing,
        ];
    }
}
