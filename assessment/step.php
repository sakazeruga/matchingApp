<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/ScoringEngine.php';

if (session_status() === PHP_SESSION_NONE) session_start();

const PER_PAGE = 8;

$applicant_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
             ?: filter_input(INPUT_POST, 'applicant_id', FILTER_VALIDATE_INT);
$type = $_GET['type'] ?? $_POST['type'] ?? '';

if (!$applicant_id || !in_array($type, ['love_score', 'cute_score'], true)) {
    header('Location: index.php');
    exit;
}

$engine    = new ScoringEngine($type);
$questions = $engine->getAllQuestions();
$total_q   = count($questions);
$total_pages = (int)ceil($total_q / PER_PAGE);

// セッションキー
$sess_key = "assessment_{$type}_{$applicant_id}";
if (!isset($_SESSION[$sess_key])) {
    $_SESSION[$sess_key] = ['answers' => [], 'page' => 1];
}

// POST: 回答を保存して次ページへ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $page = (int)($_POST['page'] ?? 1);
    $start = ($page - 1) * PER_PAGE;
    $page_qs = array_slice($questions, $start, PER_PAGE);

    foreach ($page_qs as $q) {
        $key = 'q' . $q['id'];
        $val = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
        if ($val && $val >= 1 && $val <= 5) {
            $_SESSION[$sess_key]['answers'][$q['id']] = $val;
        }
    }

    $next_page = $page + 1;
    if ($next_page > $total_pages) {
        // 全回答完了 → 採点・保存
        header("Location: submit.php?id={$applicant_id}&type={$type}");
        exit;
    }
    $_SESSION[$sess_key]['page'] = $next_page;
    header("Location: step.php?id={$applicant_id}&type={$type}&page={$next_page}");
    exit;
}

$current_page = (int)($_GET['page'] ?? $_SESSION[$sess_key]['page'] ?? 1);
$current_page = max(1, min($current_page, $total_pages));
$start        = ($current_page - 1) * PER_PAGE;
$page_qs      = array_slice($questions, $start, PER_PAGE);
$answered     = count($_SESSION[$sess_key]['answers']);
$progress     = round($answered / $total_q * 100);

$type_label = $type === 'love_score' ? '恋愛偏差値' : '可愛さ指数';

$likert_labels = ['1'=>'全くそう思わない','2'=>'そう思わない','3'=>'どちらでもない','4'=>'そう思う','5'=>'とてもそう思う'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($type_label) ?> | アセスメント</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Hiragino Kaku Gothic ProN','Meiryo',sans-serif;
            background: #f4f6fb; color: #333; padding: 0 0 3rem;
        }
        .topbar {
            background: #fff; border-bottom: 1px solid #e8edf2;
            padding: 1rem 1.5rem; position: sticky; top: 0; z-index: 10;
        }
        .topbar-inner {
            max-width: 680px; margin: 0 auto;
            display: flex; align-items: center; gap: 1rem;
        }
        .topbar h1 { font-size: 1rem; color: #2c3e50; flex: 1; }
        .progress-wrap { flex: 2; }
        .progress-bar {
            height: 8px; background: #e8edf2; border-radius: 4px; overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg,#667eea,#764ba2);
            border-radius: 4px; transition: width .3s;
        }
        .progress-text { font-size: .75rem; color: #888; margin-top: .2rem; }
        .container { max-width: 680px; margin: 1.5rem auto; padding: 0 1rem; }
        .page-indicator { font-size: .85rem; color: #888; margin-bottom: 1.2rem; }
        .q-card {
            background: #fff; border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
            padding: 1.5rem; margin-bottom: 1rem;
        }
        .q-header { display: flex; align-items: flex-start; gap: .7rem; margin-bottom: 1rem; }
        .q-num {
            min-width: 28px; height: 28px; border-radius: 50%;
            background: linear-gradient(135deg,#667eea,#764ba2);
            color: #fff; font-size: .8rem; display: flex;
            align-items: center; justify-content: center;
            font-weight: bold; flex-shrink: 0;
        }
        .q-text { font-size: .97rem; line-height: 1.6; }
        /* Likert */
        .likert { display: flex; gap: .4rem; flex-wrap: wrap; }
        .likert-opt { flex: 1; min-width: 52px; }
        .likert-opt input[type="radio"] { display: none; }
        .likert-opt label {
            display: flex; flex-direction: column; align-items: center;
            padding: .5rem .3rem; border: 1px solid #ddd; border-radius: 8px;
            cursor: pointer; font-size: .72rem; color: #888; text-align: center;
            line-height: 1.3; transition: all .12s; gap: .3rem;
        }
        .likert-opt label .dot {
            width: 20px; height: 20px; border-radius: 50%;
            border: 2px solid #ddd; display: flex; align-items: center;
            justify-content: center; font-size: .8rem; font-weight: bold;
            color: #bbb; transition: all .12s;
        }
        .likert-opt input:checked + label {
            background: #f3f0ff; border-color: #667eea; color: #5a4fcf;
        }
        .likert-opt input:checked + label .dot {
            background: #667eea; border-color: #667eea; color: #fff;
        }
        /* Scenario */
        .scenario { display: flex; flex-direction: column; gap: .5rem; }
        .scenario-opt input[type="radio"] { display: none; }
        .scenario-opt label {
            display: flex; align-items: center; gap: .7rem;
            padding: .7rem 1rem; border: 1px solid #ddd; border-radius: 8px;
            cursor: pointer; font-size: .9rem; transition: all .12s;
        }
        .scenario-opt label::before {
            content: ''; min-width: 18px; height: 18px; border-radius: 50%;
            border: 2px solid #ddd; transition: all .12s;
        }
        .scenario-opt input:checked + label {
            background: #f3f0ff; border-color: #667eea;
        }
        .scenario-opt input:checked + label::before {
            background: #667eea; border-color: #667eea;
        }
        .nav { display: flex; gap: 1rem; max-width: 680px; margin: 1.5rem auto; padding: 0 1rem; }
        .btn-next {
            flex: 1; padding: .85rem; background: linear-gradient(135deg,#667eea,#764ba2);
            color: #fff; border: none; border-radius: 10px; font-size: 1rem;
            cursor: pointer; transition: opacity .15s;
        }
        .btn-next:hover { opacity: .88; }
        .required-note { font-size: .78rem; color: #f39c12; text-align: center; margin-top: .5rem; display: none; }
        .required-note.show { display: block; }
    </style>
</head>
<body>
<div class="topbar">
    <div class="topbar-inner">
        <h1><?= h($type_label) ?></h1>
        <div class="progress-wrap">
            <div class="progress-bar">
                <div class="progress-fill" style="width:<?= $progress ?>%"></div>
            </div>
            <div class="progress-text"><?= $answered ?> / <?= $total_q ?> 回答済み</div>
        </div>
    </div>
</div>

<div class="container">
    <p class="page-indicator">
        ページ <?= $current_page ?> / <?= $total_pages ?>
        （設問 <?= $start + 1 ?>〜<?= min($start + PER_PAGE, $total_q) ?>）
    </p>

    <form method="post" id="stepForm">
        <input type="hidden" name="applicant_id" value="<?= (int)$applicant_id ?>">
        <input type="hidden" name="type"         value="<?= h($type) ?>">
        <input type="hidden" name="page"         value="<?= $current_page ?>">

        <?php foreach ($page_qs as $idx => $q):
            $q_num   = $start + $idx + 1;
            $saved   = $_SESSION[$sess_key]['answers'][$q['id']] ?? null;
            $input_name = 'q' . $q['id'];
        ?>
        <div class="q-card" data-required="1">
            <div class="q-header">
                <div class="q-num"><?= $q_num ?></div>
                <div class="q-text"><?= h($q['text']) ?></div>
            </div>

            <?php if ($q['q_type'] === 'scenario'): ?>
            <div class="scenario">
                <?php foreach ($q['options'] as $opt): ?>
                <div class="scenario-opt">
                    <input type="radio" id="<?= $input_name ?>_<?= $opt['score'] ?>"
                           name="<?= $input_name ?>" value="<?= (int)$opt['score'] ?>"
                           <?= $saved == $opt['score'] ? 'checked' : '' ?> required>
                    <label for="<?= $input_name ?>_<?= $opt['score'] ?>">
                        <?= h($opt['label']) ?>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <div class="likert">
                <?php for ($v = 1; $v <= 5; $v++): ?>
                <div class="likert-opt">
                    <input type="radio" id="<?= $input_name ?>_<?= $v ?>"
                           name="<?= $input_name ?>" value="<?= $v ?>"
                           <?= $saved == $v ? 'checked' : '' ?> required>
                    <label for="<?= $input_name ?>_<?= $v ?>">
                        <span class="dot"><?= $v ?></span>
                        <?= h($likert_labels[(string)$v]) ?>
                    </label>
                </div>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="nav">
            <button type="submit" class="btn-next" id="nextBtn">
                <?= $current_page < $total_pages ? '次へ →' : '回答を完了する' ?>
            </button>
        </div>
        <p class="required-note" id="reqNote">未回答の設問があります。すべて選択してください。</p>
    </form>
</div>

<script>
document.getElementById('stepForm').addEventListener('submit', function(e) {
    const cards = document.querySelectorAll('[data-required]');
    let ok = true;
    cards.forEach(card => {
        const name = card.querySelector('input[type="radio"]')?.name;
        if (name && !document.querySelector(`input[name="${name}"]:checked`)) {
            ok = false;
            card.style.borderLeft = '3px solid #e74c3c';
        } else if (name) {
            card.style.borderLeft = '';
        }
    });
    if (!ok) {
        e.preventDefault();
        document.getElementById('reqNote').classList.add('show');
        document.querySelector('[data-required]').scrollIntoView({behavior:'smooth'});
    }
});
</script>
</body>
</html>
