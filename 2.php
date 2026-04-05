<?php
/**
 * SYSTEME DE TRAITEMENT IA v3.0 - ÉTAPE 1
 * Version robuste : Single-thread, 1 requête/minute, gestion d'erreurs JSON
 * Renommage : succès → etape1-XXX.json | échec → off-XXX.json
 */

$storageDir = __DIR__ . '/1';
$mistralApiKey = ' your api key here '; // 🔐 À sécuriser en production

// --- LOGIQUE SERVEUR (API INTERNE) ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // Lister les fichiers à traiter
    if ($_GET['action'] === 'list') {
        $files = glob($storageDir . '/*.json');
        $todo = [];
        foreach ($files as $file) {
            $name = basename($file);
            // Exclure les fichiers déjà traités (préfixes etape1- ou off-)
            if (strpos($name, 'etape1-') !== 0 
                && strpos($name, 'off-') !== 0 
                && strpos($name, '.json') === strlen($name) - 5) {
                $content = @file_get_contents($file);
                if ($content !== false) {
                    $decoded = json_decode($content, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $todo[] = ['filename' => $name, 'content' => $decoded];
                    } else {
                        // Fichier JSON corrompu → on le marque comme "off" immédiatement
                        $newName = $storageDir . '/off-CORRUPT-' . $name;
                        @rename($file, $newName);
                        error_log("JSON invalide déplacé : $name");
                    }
                }
            }
        }
        // Tri par date de création (plus ancien en premier)
        usort($todo, function($a, $b) use ($storageDir) {
            return filemtime($storageDir.'/'.$a['filename']) <=> filemtime($storageDir.'/'.$b['filename']);
        });
        echo json_encode(['status' => 'success', 'data' => $todo]);
        exit;
    }

    // Sauvegarder le résultat de l'IA (succès ou échec)
    if ($_GET['action'] === 'save') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['filename'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Données invalides : ' . json_last_error_msg()]);
            exit;
        }
        
        $oldPath = $storageDir . '/' . $data['filename'];
        $success = ($data['status'] ?? '') === 'success';
        $prefix = $success ? 'etape1-' : 'off-';
        $newPath = $storageDir . '/' . $prefix . $data['filename'];
        
        if (!file_exists($oldPath)) {
            echo json_encode(['status' => 'error', 'message' => 'Fichier source introuvable']);
            exit;
        }
        
        // Chargement du contenu original
        $content = @json_decode(file_get_contents($oldPath), true);
        if (!is_array($content)) {
            $content = [];
        }
        
        if ($success) {
            // En cas de succès : on ajoute l'analyse IA
            $content['ai_analysis'] = $data['ai_response'] ?? '';
            $content['processed_at'] = date('Y-m-d H:i:s');
            $content['model_used'] = 'pixtral-12b-2409';
            $content['processing_status'] = 'completed';
        } else {
            // En cas d'échec : on log l'erreur
            $content['processing_error'] = $data['error_message'] ?? 'Erreur inconnue';
            $content['failed_at'] = date('Y-m-d H:i:s');
            $content['processing_status'] = 'failed';
        }
        
        $encoded = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        
        if (file_put_contents($newPath, $encoded) !== false) {
            @unlink($oldPath);
            echo json_encode(['status' => 'success', 'new_filename' => basename($newPath)]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Échec de l\'écriture sur le disque']);
        }
        exit;
    }
    
    // Endpoint santé
    if ($_GET['action'] === 'status') {
        echo json_encode(['status' => 'ready', 'timestamp' => time()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>IA Processor v3.0 | Science Pulse</title>
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --accent: #38bdf8; --success: #22c55e; --error: #ef4444; --warn: #f59e0b; --off: #64748b; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: #f8fafc; margin: 0; padding: 20px; }
        .container { max-width: 1100px; margin: auto; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid #334155; padding-bottom: 20px; }
        .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .panel { background: var(--card); padding: 20px; border-radius: 12px; border: 1px solid #334155; margin-bottom: 20px; }
        .progress-container { background: #334155; height: 12px; border-radius: 6px; margin: 20px 0; overflow: hidden; }
        #progress-fill { background: var(--accent); width: 0%; height: 100%; transition: width 0.4s ease; }
        .console { background: #000; border-radius: 8px; padding: 15px; height: 450px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 13px; border: 1px solid #334155; }
        .log { margin-bottom: 4px; padding: 4px 8px; border-radius: 4px; border-left: 3px solid transparent; white-space: pre-wrap; word-break: break-word; }
        .log-info { color: var(--accent); border-color: var(--accent); background: rgba(56, 189, 248, 0.05); }
        .log-success { color: var(--success); border-color: var(--success); background: rgba(34, 197, 94, 0.05); }
        .log-error { color: var(--error); border-color: var(--error); background: rgba(239, 68, 68, 0.05); }
        .log-warn { color: var(--warn); border-color: var(--warn); background: rgba(245, 158, 11, 0.05); }
        .log-off { color: var(--off); border-color: var(--off); background: rgba(100, 116, 139, 0.1); }
        .btn { background: var(--accent); color: #0f172a; border: none; padding: 15px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; font-size: 1rem; transition: opacity 0.2s; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn:hover:not(:disabled) { opacity: 0.9; }
        .badge { font-size: 0.8rem; padding: 4px 10px; border-radius: 20px; background: #334155; }
        .current-item { font-size: 0.9rem; color: #94a3b8; margin-top: 10px; min-height: 1.2em; }
        code { background: #0f172a; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
        .legend { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; font-size: 0.8rem; }
        .legend span { display: flex; align-items: center; gap: 4px; }
        .legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>Science Pulse <span style="color:var(--accent)">Stage 1 v3.0</span></h1>
        <span id="fileCount" class="badge">Chargement...</span>
    </header>

    <div class="grid">
        <section>
            <div class="panel">
                <h3 id="statusText">Système prêt — 1 requête/minute</h3>
                <div class="progress-container"><div id="progress-fill"></div></div>
                <div id="currentItem" class="current-item"></div>
                <button id="startBtn" class="btn">LANCER LE TRAITEMENT SÉQUENTIEL</button>
                <div class="legend">
                    <span><i class="legend-dot" style="background:var(--success)"></i> etape1- = succès</span>
                    <span><i class="legend-dot" style="background:var(--error)"></i> off- = échec</span>
                </div>
            </div>
            <div class="console" id="console">
                <div class="log log-info">> Système initialisé. Mode : Single-thread + 60s entre requêtes.</div>
                <div class="log log-info">> Gestion d'erreurs JSON activée.</div>
                <div class="log log-info">> Succès → prefix "etape1-" | Échec → prefix "off-"</div>
            </div>
        </section>

        <aside>
            <div class="panel">
                <h4>⚙️ Paramètres</h4>
                <p>Modèle: <code>pixtral-12b-2409</code></p>
                <p>Mode: <code>Single-thread</code></p>
                <p>Délai: <code>60 000ms (1 min)</code></p>
                <p>Retries: <code>1</code> (pas de surcharge)</p>
                <p>Timeout: <code>60s</code></p>
            </div>
            <div class="panel">
                <h4>📊 Statistiques</h4>
                <p>Traités: <span id="statDone">0</span></p>
                <p>Succès: <span id="statSuccess" style="color:var(--success)">0</span></p>
                <p>Échecs: <span id="statFailed" style="color:var(--error)">0</span></p>
                <p>Restants: <span id="statRemaining">0</span></p>
            </div>
        </aside>
    </div>
</div>

<script>
// 🔐 Configuration
const CONFIG = {
    MISTRAL_API_KEY: '<?= addslashes($mistralApiKey) ?>',
    API_URL: 'https://api.mistral.ai/v1/chat/completions',
    MODEL: 'pixtral-12b-2409',
    MAX_TOKENS: 4000,
    REQUEST_DELAY_MS: 600,       // ⏱️ 1 minute entre chaque requête
    MAX_RETRIES: 1,                // 1 seule retry pour éviter la saturation
    RETRY_BASE_DELAY_MS: 2000,
    TIMEOUT_MS: 60000
};

const PROMPT_BASE = `Tu es une IA experte en bio-informatique et en ingénierie logicielle. Ton rôle est de transformer l'article scientifique fourni en un modèle de recherche actionnable où l'on utilisera des fonctions PHP et des APIs de données.

CONTEXTE DE L'ARTICLE :
{ARTICLE_CONTENT}

MISSION :
Rédige une étude approfondie expliquant comment une infrastructure basée sur PHP 8.3 et une base de données SQLite/Vectorielle peut faire avancer la science sur le sujet de l'article. Tu dois détailler des expériences numériques concrètes, expérimentales et révolutionnaires.

STRUCTURE DE TA RÉPONSE (FORMAT ACADÉMIQUE) :

1. ANALYSE CRITIQUE : Résume les points clés de l'article et identifie une lacune de données ou un besoin de corrélation spécifique.

2. PROTOCOLE D'EXPÉRIMENTATION PHP : Détermine comment un script PHP pourrait automatiser la collecte de données via les API ci-dessous pour valider ou infirmer les hypothèses de l'article.

3. UTILISATION STRATÉGIQUE DES API (OBLIGATOIRE) : 
Pour chaque groupe d'API suivant dont tu connais le fonctionnement des endpoints, explique précisément quelle donnée extraire (en remplaçant {TERM} par un mot-clé pertinent de l'article) et comment l'intégrer dans l'étude :

   - RECHERCHE GÉNÉRALE & LITTÉRATURE : PubMed, EuropePMC, OpenAlex, CrossRef, arXiv, SemanticScholar.
   - DONNÉES GÉNOMIQUES & PROTÉINES : UniProt, Ensembl, ClinVar, NCBI_Gene, NCBI_Protein, PDB.
   - BIOLOGIE DES SYSTÈMES & VOIES : StringDB, Reactome, GeneOntology, KEGG.
   - CLINIQUE & PHARMACOLOGIE : ClinicalTrials, OpenFDA, ChEMBL, PubChem, RxNorm, DisGeNET.
   - ÉCOLOGIE & BIODIVERSITÉ : GBIF, WorldBank, WHOGHO.
   - IA & MODÈLES : HuggingFace, PapersWithCode.

4. ARCHITECTURE BDD & ALGORITHME :
   - Propose un schéma de table SQLite pour stocker ces résultats corrélés.
   - Explique comment PHP pourrait traiter ces volumes (multi-threading, parsing JSON) et surtout comment PHP pourrait grâce à ses fonctions faire des calculs et expériences réelles.

5. IMPACT SCIENTIFIQUE ATTENDU : En quoi cette automatisation par le code apporte-t-elle une valeur que l'article original n'avait pas ?

CONSIGNES DE RÉDACTION :
- Style : Professionnel, technique, académique.
- Langue : Français.
- Pas d'introduction inutile du type "Voici mon analyse". Entre directement dans le vif du sujet.
- Utilise des termes techniques (exemple : API, Endpoints, Parsing, Corrélation, Ontologie).`;

// État global
let queue = [];
let totalCount = 0;
let successCount = 0;
let failedCount = 0;
let isRunning = false;

// Logging
function addLog(msg, type = 'info') {
    const el = document.getElementById('console');
    const div = document.createElement('div');
    div.className = `log log-${type}`;
    const timestamp = new Date().toLocaleTimeString('fr-FR');
    div.innerHTML = `<span style="opacity:0.7">[${timestamp}]</span> ${escapeHtml(msg)}`;
    el.appendChild(div);
    el.scrollTop = el.scrollHeight;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function updateStats() {
    document.getElementById('statDone').textContent = successCount + failedCount;
    document.getElementById('statSuccess').textContent = successCount;
    document.getElementById('statFailed').textContent = failedCount;
    document.getElementById('statRemaining').textContent = queue.length;
    const percent = totalCount > 0 ? Math.round(((successCount + failedCount) / totalCount) * 100) : 0;
    document.getElementById('progress-fill').style.width = `${percent}%`;
    document.getElementById('fileCount').textContent = `${queue.length} en attente`;
}

function setCurrentItem(filename) {
    const el = document.getElementById('currentItem');
    if (filename) {
        const shortName = filename.length > 50 ? filename.substring(0, 47) + '...' : filename;
        el.textContent = `🔄 Traitement : ${shortName}`;
    } else {
        el.textContent = '';
    }
}

// Chargement de la file
async function refreshList() {
    try {
        const res = await fetch('?action=list', { cache: 'no-store' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const result = await res.json();
        if (result.status === 'success' && Array.isArray(result.data)) {
            queue = result.data;
            totalCount = queue.length;
            successCount = 0;
            failedCount = 0;
            updateStats();
            addLog(`📋 ${totalCount} articles chargés en file d'attente.`, 'info');
            if (totalCount === 0) {
                addLog('⚠️ Aucun article à traiter. Lancez d\'abord la mise à jour depuis 1.php', 'warn');
            }
        } else {
            throw new Error('Format de réponse invalide');
        }
    } catch (e) {
        addLog(`❌ Échec du chargement : ${e.message}`, 'error');
        console.error(e);
    }
}

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function withRetry(fn, maxRetries = CONFIG.MAX_RETRIES, attempt = 0) {
    try {
        return await fn();
    } catch (error) {
        if (attempt >= maxRetries) throw error;
        const delayMs = CONFIG.RETRY_BASE_DELAY_MS * Math.pow(2, attempt);
        addLog(`⚠️ Tentative ${attempt + 1}/${maxRetries} échouée. Retry dans ${delayMs}ms...`, 'warn');
        await delay(delayMs);
        return withRetry(fn, maxRetries, attempt + 1);
    }
}

// Traitement d'un article
async function processOne(article) {
    if (!article || !article.filename || !article.content) {
        return { success: false, error: 'Article invalide' };
    }

    const filename = article.filename;
    addLog(`🔍 Préparation : ${filename}`, 'info');
    setCurrentItem(filename);

    const articleContent = JSON.stringify(article.content, null, 2);
    const prompt = PROMPT_BASE.replace('{ARTICLE_CONTENT}', articleContent);

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), CONFIG.TIMEOUT_MS);

    try {
        const response = await withRetry(async () => {
            const res = await fetch(CONFIG.API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${CONFIG.MISTRAL_API_KEY}`
                },
                body: JSON.stringify({
                    model: CONFIG.MODEL,
                    messages: [{ role: 'user', content: prompt }],
                    max_tokens: CONFIG.MAX_TOKENS,
                    temperature: 0.3
                }),
                signal: controller.signal
            });
            
            if (!res.ok) {
                const errText = await res.text().catch(() => ' corps non lisible');
                if (res.status === 429) throw new Error(`Rate limit 429 — quota atteint`);
                if (res.status === 401) throw new Error(`Clé API invalide (401)`);
                if (res.status === 400) throw new Error(`Requête invalide (400)`);
                throw new Error(`HTTP ${res.status} : ${errText.substring(0, 150)}`);
            }
            return res;
        });

        clearTimeout(timeoutId);

        const responseText = await response.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Raw:', responseText.substring(0, 300));
            throw new Error(`Échec parsing JSON : ${parseError.message}`);
        }

        // Validation structure Mistral
        if (!data?.choices?.[0]?.message?.content) {
            if (data?.error?.message) throw new Error(`Mistral: ${data.error.message}`);
            throw new Error('Structure de réponse invalide');
        }

        const aiText = data.choices[0].message.content.trim();
        if (!aiText) throw new Error('Réponse vide');

        // Sauvegarde SUCCÈS → prefix etape1-
        addLog(`💾 Sauvegarde (succès) : ${filename} → etape1-${filename}`, 'info');
        const saveRes = await fetch('?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                filename: filename,
                content: article.content,
                ai_response: aiText,
                status: 'success'
            })
        });

        if (!saveRes.ok) throw new Error(`Échec sauvegarde HTTP ${saveRes.status}`);
        const saveResult = await saveRes.json();
        if (saveResult.status !== 'success') throw new Error(saveResult.message || 'Sauvegarde refusée');

        addLog(`✅ ${filename} → etape1-${filename} [SUCCÈS]`, 'success');
        return { success: true };

    } catch (error) {
        clearTimeout(timeoutId);
        const msg = error.message || String(error);
        
        // Log contextuel
        if (msg.includes('429')) {
            addLog(`⚠️ ${filename} : Quota Mistral atteint`, 'warn');
        } else if (msg.includes('JSON')) {
            addLog(`❌ ${filename} : Erreur format JSON`, 'error');
        } else if (msg.includes('401')) {
            addLog(`🔐 ${filename} : Clé API invalide`, 'error');
        } else if (msg.includes('timeout') || msg.includes('abort')) {
            addLog(`⏱️ ${filename} : Timeout requête`, 'error');
        } else {
            addLog(`❌ ${filename} : ${msg}`, 'error');
        }
        console.error(`Erreur ${filename}:`, error);

        // Sauvegarde ÉCHEC → prefix off-
        try {
            addLog(`💾 Sauvegarde (échec) : ${filename} → off-${filename}`, 'info');
            await fetch('?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    filename: filename,
                    content: article.content,
                    error_message: msg,
                    status: 'failed'
                })
            });
            addLog(`⚠️ ${filename} → off-${filename} [ÉCHEC]`, 'off');
        } catch (saveErr) {
            addLog(`❌ Échec sauvegarde erreur pour ${filename}`, 'error');
        }

        return { success: false, error: msg };
    } finally {
        setCurrentItem(null);
    }
}

// Boucle principale
async function run() {
    if (isRunning) return;
    isRunning = true;
    
    const btn = document.getElementById('startBtn');
    const status = document.getElementById('statusText');
    
    btn.disabled = true;
    status.textContent = 'Traitement en cours (1/min)...';
    addLog('🚀 Démarrage du traitement séquentiel...', 'info');

    if (queue.length === 0) await refreshList();

    while (queue.length > 0 && isRunning) {
        const article = queue.shift();
        const result = await processOne(article);
        
        if (result.success) {
            successCount++;
        } else {
            failedCount++;
        }
        
        updateStats();
        
        // Pause 1 minute avant le prochain (sauf si file vide)
        if (queue.length > 0) {
            addLog(`⏳ Pause 60s avant le prochain article...`, 'info');
            await delay(CONFIG.REQUEST_DELAY_MS);
        }
    }

    isRunning = false;
    btn.disabled = false;
    status.textContent = queue.length === 0 ? '✅ Session terminée' : '⏸️ Pause';
    
    if (queue.length === 0) {
        const total = successCount + failedCount;
        const rate = total > 0 ? Math.round((successCount / total) * 100) : 0;
        addLog(`🏁 Terminé : ${successCount}✅ / ${failedCount}❌ (${rate}% succès)`, successCount > 0 ? 'success' : 'info');
        await refreshList();
    } else {
        addLog(`⏸️ Interruption. ${queue.length} restants.`, 'warn');
    }
}

// Events
document.getElementById('startBtn').addEventListener('click', () => {
    if (!isRunning) run();
});

document.getElementById('fileCount').addEventListener('click', (e) => {
    if (!isRunning) {
        e.preventDefault();
        refreshList();
        addLog('🔄 Liste rafraîchie', 'info');
    }
});

document.addEventListener('DOMContentLoaded', () => {
    refreshList();
    addLog('🔌 Interface prête. 1 requête/minute pour respecter le quota Mistral.', 'info');
});

window.addEventListener('beforeunload', () => {
    if (isRunning) isRunning = false;
});
</script>
</body>
</html>