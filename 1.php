<?php
/**
 * SYSTEME DE VEILLE SCIENTIFIQUE MEDICALE v1.1
 * - Debug Console incluse
 */

// --- CONFIGURATION ---
$storageDir = __DIR__ . '/1';
$dbFile = $storageDir . '/veille_science.sqlite';
$limit = 20; 
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Initialisation du log de debug
$debugLog = [];

// 1. INITIALISATION
if (!file_exists($storageDir)) {
    if(mkdir($storageDir, 0755, true)) {
        $debugLog[] = "✅ Dossier de stockage créé.";
    } else {
        $debugLog[] = "❌ Impossible de créer le dossier $storageDir";
    }
}

try {
    $db = new PDO("sqlite:$dbFile");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS articles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        source TEXT,
        title TEXT,
        link TEXT UNIQUE,
        description TEXT,
        date_fetched DATETIME DEFAULT CURRENT_TIMESTAMP,
        raw_json TEXT
    )");
} catch (Exception $e) {
    $debugLog[] = "❌ Erreur BDD : " . $e->getMessage();
}

// 2. LOGIQUE DE MISE À JOUR (CRAWLER)
if (isset($_GET['update'])) {
    $sources = [
        'arXiv q-bio' => 'https://export.arxiv.org/rss/q-bio',
        'ScienceDaily' => 'https://www.sciencedaily.com/rss/top/health.xml',
        'ClinicalTrials' => 'https://clinicaltrials.gov/ct2/results/rss.xml?rsch=adv',
        'The Lancet' => 'https://www.thelancet.com/rssfeed/lancet_online.xml',
        'Inserm' => 'https://www.inserm.fr/feed/',
        'Pour la Science' => 'https://www.pourlascience.fr/vivant/rss.xml'
    ];

    foreach ($sources as $name => $url) {
        $debugLog[] = "🔍 Tentative sur : $name...";
        $xmlContent = @file_get_contents($url);
        
        if ($xmlContent === false) {
            $debugLog[] = "   ❌ Échec du téléchargement (URL injoignable)";
            continue;
        }

        $xml = @simplexml_load_string($xmlContent);
        if (!$xml) {
            $debugLog[] = "   ❌ XML mal formé pour $name";
            continue;
        }

        $items = isset($xml->channel->item) ? $xml->channel->item : $xml->entry;
        $count = 0;

        foreach ($items as $item) {
            $title = (string)$item->title;
            $link = (string)($item->link['href'] ?? $item->link);
            $desc = (string)($item->description ?? $item->summary ?? '');
            
            $data = ['source' => $name, 'title' => $title, 'link' => $link, 'desc' => $desc];
            $jsonStr = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            // Sauvegarde JSON
            @file_put_contents($storageDir . '/' . md5($link) . '.json', $jsonStr);

            // Sauvegarde SQLite
            try {
                $stmt = $db->prepare("INSERT OR IGNORE INTO articles (source, title, link, description, raw_json) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $title, $link, $desc, $jsonStr]);
                if ($stmt->rowCount() > 0) $count++;
            } catch (Exception $e) {
                $debugLog[] = "   ❌ Erreur insertion article : " . $e->getMessage();
            }
        }
        $debugLog[] = "   ✅ Terminé : $count nouveaux articles ajoutés.";
    }
    // Note: on ne fait pas de redirection immédiate pour pouvoir lire le log de debug
    // si l'on veut voir le debug sur la même page.
}

// 3. RÉCUPÉRATION DES DONNÉES
$totalArticles = $db->query("SELECT COUNT(*) FROM articles")->fetchColumn();
$totalPages = ceil($totalArticles / $limit);

$stmt = $db->prepare("SELECT * FROM articles ORDER BY date_fetched DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Veille Scientifique (Page <?= $page ?>)</title>
    <style>
        :root { --bg: #f4f7f6; --text: #2c3e50; --accent: #007bff; --debug-bg: #222; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); margin: 0; line-height: 1.6; }
        header { background: #fff; padding: 1rem 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: #fff; padding: 1.5rem; border-radius: 10px; margin-bottom: 1.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .source { font-size: 0.75rem; font-weight: bold; color: var(--accent); text-transform: uppercase; letter-spacing: 1px; }
        h2 { margin: 0.5rem 0; font-size: 1.25rem; color: #1a1a1a; }
        .desc { font-size: 0.95rem; color: #555; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .btn { background: var(--accent); color: white; padding: 0.6rem 1.2rem; border-radius: 5px; text-decoration: none; font-size: 0.9rem; border:none; cursor:pointer;}
        
        /* Console Debug */
        .debug-console { background: var(--debug-bg); color: #00ff00; font-family: 'Courier New', monospace; padding: 1rem; margin: 2rem; border-radius: 5px; font-size: 12px; max-height: 300px; overflow-y: auto; border: 2px solid #444; }
        .debug-title { color: #fff; margin-bottom: 5px; font-weight: bold; border-bottom: 1px solid #444; padding-bottom: 5px; }

        .pagination { display: flex; justify-content: center; gap: 1rem; margin: 2rem 0; align-items: center; }
        .page-link { padding: 8px 16px; background: #fff; border: 1px solid #ddd; color: var(--text); text-decoration: none; border-radius: 4px; }
        .page-link.active { background: var(--accent); color: #fff; border-color: var(--accent); }
        .page-link.disabled { color: #ccc; pointer-events: none; }
    </style>
</head>
<body>

<header>
    <h1>Science Pulse 🔬</h1>
    <a href="?update=1" class="btn">Mettre à jour</a>
</header>

<div class="container">
    
    <?php if (!empty($debugLog)): ?>
    <div class="debug-console">
        <div class="debug-title">DEBUG CONSOLE [<?= date('H:i:s') ?>]</div>
        <?php foreach ($debugLog as $log): ?>
            <div><?= htmlspecialchars($log) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($articles)): ?>
        <p style="text-align:center;">Aucun article trouvé. Cliquez sur "Mettre à jour".</p>
    <?php else: ?>
        <?php foreach ($articles as $a): ?>
            <div class="card">
                <div class="source"><?= htmlspecialchars($a['source']) ?> • <?= date('d M Y', strtotime($a['date_fetched'])) ?></div>
                <h2><?= htmlspecialchars($a['title']) ?></h2>
                <div class="desc"><?= strip_tags($a['description'] ?? '') ?></div>
                <a href="<?= htmlspecialchars($a['link']) ?>" target="_blank" style="color: var(--accent); display:block; margin-top:10px; font-weight:bold;">Lire la suite →</a>
            </div>
        <?php endforeach; ?>

        <div class="pagination">
            <a href="?p=<?= $page - 1 ?>" class="page-link <?= ($page <= 1) ? 'disabled' : '' ?>">« Précédent</a>
            <span class="info">Page <strong><?= $page ?></strong> sur <?= $totalPages ?></span>
            <a href="?p=<?= $page + 1 ?>" class="page-link <?= ($page >= $totalPages) ? 'disabled' : '' ?>">Suivant »</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>