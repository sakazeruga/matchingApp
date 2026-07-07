<?php
declare(strict_types=1);
require_once __DIR__ . '/questions.php';

/**
 * アセスメント採点エンジン
 *
 * 【スコア計算アルゴリズム】
 *  1. 各設問を 1-5 スケールで取得（reverse_likert は 6 - raw で反転）
 *  2. カテゴリスコア = カテゴリ内の平均点を 0-100 に正規化
 *  3. 総合スコア = Σ(カテゴリスコア × 正規化済みウェイト)
 *  4. 要注意フラグ: is_flag_trigger の設問が flag_threshold 以下
 *  5. K カテゴリ平均 < 30 → 自己認識フラグ（他が高くても警告）
 */
class ScoringEngine
{
    private string $type;
    /** @var array<int, array> question_id => question data */
    private array $q_map;
    /** @var array<string, array<int>> category => [question_id, ...] */
    private array $cat_q_map;
    /** @var array<string, float> 正規化済みウェイト */
    private array $norm_weights;

    public function __construct(string $assessment_type)
    {
        $this->type = $assessment_type;
        $this->buildMaps();
        $this->norm_weights = $this->normalizeWeights(
            CATEGORY_WEIGHTS[$assessment_type]
        );
    }

    // ──────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────

    /**
     * スコアを計算する
     *
     * @param array<int, int> $responses  [question_id => raw_score(1-5)]
     * @return array{
     *   total: float,
     *   categories: array<string, float>,
     *   warnings: array,
     *   has_warning: bool
     * }
     */
    public function calculate(array $responses): array
    {
        $adj   = $this->applyReverse($responses);
        $cats  = $this->calcCategoryScores($adj);
        $total = $this->calcTotal($cats);
        $warns = $this->detectWarnings($adj, $cats);

        return [
            'total'      => round($total, 2),
            'categories' => array_map(fn($v) => round($v, 2), $cats),
            'warnings'   => $warns,
            'has_warning'=> count($warns) > 0,
        ];
    }

    /**
     * 設問リストをカテゴリ順で返す（フォーム表示用）
     * @return array<string, array> [category_code => [questions]]
     */
    public function getQuestionsByCategory(): array
    {
        $result = [];
        foreach ($this->cat_q_map as $cat => $ids) {
            foreach ($ids as $id) {
                $result[$cat][] = array_merge(['id' => $id], $this->q_map[$id]);
            }
        }
        return $result;
    }

    /**
     * 全設問を表示順で返す（フォーム表示用）
     * @return array<int, array>
     */
    public function getAllQuestions(): array
    {
        $qs = [];
        foreach ($this->q_map as $id => $q) {
            $qs[] = array_merge(['id' => $id], $q);
        }
        usort($qs, fn($a, $b) => $a['order'] <=> $b['order']);
        return $qs;
    }

    public function getType(): string { return $this->type; }
    public function getNormWeights(): array { return $this->norm_weights; }

    // ──────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────

    private function buildMaps(): void
    {
        $idx = 1;
        foreach (ASSESSMENT_QUESTIONS as $q) {
            if ($q['type'] !== $this->type) { $idx++; continue; }
            $this->q_map[$idx] = $q;
            $this->cat_q_map[$q['cat']][] = $idx;
            $idx++;
        }
    }

    /** reverse_likert の反転処理 */
    private function applyReverse(array $responses): array
    {
        $adj = [];
        foreach ($responses as $qid => $raw) {
            $q = $this->q_map[$qid] ?? null;
            if (!$q) continue;
            $adj[$qid] = ($q['q_type'] === 'reverse_likert')
                ? (6 - (int)$raw)
                : (int)$raw;
        }
        return $adj;
    }

    /** カテゴリごとの 0-100 スコアを計算 */
    private function calcCategoryScores(array $adj): array
    {
        $scores = [];
        foreach ($this->cat_q_map as $cat => $ids) {
            $vals = [];
            foreach ($ids as $qid) {
                if (isset($adj[$qid])) {
                    $vals[] = $adj[$qid];
                }
            }
            if (count($vals) === 0) {
                $scores[$cat] = 0.0;
                continue;
            }
            // 1-5 スケール → 0-100 に正規化
            $avg = array_sum($vals) / count($vals);
            $scores[$cat] = ($avg - 1) / 4 * 100;
        }
        return $scores;
    }

    /** 重み付き合計スコア */
    private function calcTotal(array $cat_scores): float
    {
        $total = 0.0;
        foreach ($this->norm_weights as $cat => $w) {
            $total += ($cat_scores[$cat] ?? 0.0) * $w / 100;
        }
        return $total;
    }

    /** 要注意フラグ検知 */
    private function detectWarnings(array $adj, array $cat_scores): array
    {
        $warns = [];

        // 個別フラグ設問チェック
        foreach ($this->q_map as $qid => $q) {
            if (empty($q['is_flag'])) continue;
            $score = $adj[$qid] ?? null;
            if ($score === null) continue;
            $threshold = (int)($q['flag_threshold'] ?? 2);
            if ($score <= $threshold) {
                $warns[] = [
                    'type'    => ($q['cat'] === 'N') ? 'sincerity_warning' : 'self_awareness_flag',
                    'sub'     => $q['sub'],
                    'message' => $this->warnMessage($q['cat'], $q['sub']),
                    'score'   => $score,
                ];
            }
        }

        // K カテゴリ全体スコアが低い場合（30未満）
        if (isset($cat_scores['K']) && $cat_scores['K'] < 30.0) {
            $already = array_filter($warns, fn($w) => $w['type'] === 'self_awareness_flag');
            if (empty($already)) {
                $warns[] = [
                    'type'    => 'self_awareness_flag',
                    'sub'     => 'K',
                    'message' => '自己認識の客観性スコアが基準値を下回っています。成長可能性を厳格に審査します。',
                    'score'   => round($cat_scores['K'], 1),
                ];
            }
        }

        return $warns;
    }

    private function warnMessage(string $cat, string $sub): string
    {
        return match($cat) {
            'N' => "【誠実性警告】{$sub}：言動の一貫性に懸念があります。",
            'K' => "【要注意フラグ】{$sub}：自己認識の客観性に課題が見られます。",
            default => "【要注意】{$sub}：該当項目の審査を行います。",
        };
    }

    /** ウェイトを合計100に正規化 */
    private function normalizeWeights(array $weights): array
    {
        $sum = array_sum($weights);
        $result = [];
        foreach ($weights as $k => $v) {
            $result[$k] = round($v / $sum * 100, 4);
        }
        return $result;
    }
}
