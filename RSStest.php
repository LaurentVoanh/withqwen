<?php
ini_set('max_execution_time', 180);

// 1. LISTE RSS FILTRÉE (UNIQUEMENT LES FONCTIONNELS)
$rss_feeds = [
    "arXiv q-bio" => "https://export.arxiv.org/rss/q-bio",
    "ScienceDaily Health" => "https://www.sciencedaily.com/rss/top/health.xml",
    "ClinicalTrials RSS" => "https://clinicaltrials.gov/ct2/results/rss.xml?rsch=adv",
    "Inserm" => "https://www.inserm.fr/feed/",
    "ANRS" => "https://anrs.fr/feed/",
    "Pour la Science" => "https://www.pourlascience.fr/vivant/rss.xml",
    "BioWorld" => "https://www.bioworld.com/rss/7",
    "The Lancet" => "https://www.thelancet.com/rssfeed/lancet_online.xml",
    "NEJM Emergency" => "https://onesearch-rss.nejm.org/api/specialty/rss?context=nejm&specialty=emergency-medicine"
];

// 2. CONFIGURATION API FILTRÉE (SANS LES ERREURS 403/404)
$api_configs = [
    'PubMed' => ['url' => 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&retmode=json&retmax=1&term=cancer', 'type' => 'JSON'],
    'EuropePMC' => ['url' => 'https://www.ebi.ac.uk/europepmc/webservices/rest/search?format=json&pageSize=1&query=cancer', 'type' => 'JSON'],
    'OpenAlex' => ['url' => 'https://api.openalex.org/works?per_page=1&search=cancer', 'type' => 'JSON'],
    'UniProt' => ['url' => 'https://rest.uniprot.org/uniprotkb/search?format=json&size=1&query=TP53', 'type' => 'JSON'],
    'Ensembl' => ['url' => 'https://rest.ensembl.org/lookup/symbol/homo_sapiens/BRCA1?content-type=application/json', 'type' => 'JSON'],
    'ChEMBL' => ['url' => 'https://www.ebi.ac.uk/chembl/api/data/molecule.json?pref_name__icontains=aspirin&limit=1', 'type' => 'JSON'],
    'OpenFDA' => ['url' => 'https://api.fda.gov/drug/label.json?limit=1&search=indications_and_usage:aspirin', 'type' => 'JSON'],
    'Unpaywall' => ['url' => 'https://api.unpaywall.org/v2/10.1038/nature12373?email=test@test.com', 'type' => 'JSON'],
    'WHOGHO' => ['url' => "https://ghoapi.azureedge.net/api/Indicator?\$top=1", 'type' => 'JSON']
];

function fetchData($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ScientificBot/1.0');
    $data = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return ['data' => $data, 'code' => $info['http_code']];
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Explorateur de Données Scientifiques</title>
    <style>
        body { font-family: sans-serif; background: #eceff1; padding: 20px; line-height: 1.5; }
        .card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-left: 5px solid #2196F3; }
        .source-name { font-size: 1.2em; font-weight: bold; color: #0d47a1; margin-bottom: 5px; }
        .url { font-size: 0.8em; color: #666; word-break: break-all; margin-bottom: 10px; }
        .content-box { background: #f9f9f9; border: 1px inset #ddd; padding: 10px; font-size: 0.9em; max-height: 200px; overflow-y: auto; }
        .tag { font-size: 0.7em; text-transform: uppercase; padding: 2px 6px; border-radius: 3px; background: #eee; }
        .status-ok { color: green; font-weight: bold; }
    </style>
</head>
<body>

    <h1>Extraction des Flux Scientifiques Validés</h1>

    <h2>1. Contenu des Flux RSS</h2>
    <?php foreach ($rss_feeds as $name => $url): ?>
        <div class="card">
            <div class="source-name"><?= $name ?> <span class="tag">RSS</span></div>
            <div class="url"><?= $url ?></div>
            <?php 
                $res = fetchData($url);
                if ($res['code'] == 200) {
                    $xml = @simplexml_load_string($res['data']);
                    if ($xml) {
                        // Gestion des différents formats RSS/Atom
                        $item = isset($xml->channel->item[0]) ? $xml->channel->item[0] : (isset($xml->entry[0]) ? $xml->entry[0] : null);
                        if ($item) {
                            echo "<div class='content-box'>";
                            echo "<strong>Titre :</strong> " . ($item->title ?? 'N/A') . "<br>";
                            echo "<strong>Description :</strong> " . strip_tags($item->description ?? $item->summary ?? 'Pas de description');
                            echo "</div>";
                        }
                    }
                } else { echo "<p style='color:red'>Erreur " . $res['code'] . "</p>"; }
            ?>
        </div>
    <?php endforeach; ?>

    <h2>2. Contenu des API (Exemple JSON)</h2>
    <?php foreach ($api_configs as $name => $cfg): ?>
        <div class="card" style="border-left-color: #4CAF50;">
            <div class="source-name"><?= $name ?> <span class="tag">API JSON</span></div>
            <div class="url"><?= $cfg['url'] ?></div>
            <?php 
                $res = fetchData($cfg['url']);
                if ($res['code'] == 200) {
                    $json = json_decode($res['data'], true);
                    echo "<div class='content-box'><pre>";
                    // On affiche une portion réduite pour ne pas surcharger la page
                    print_r(array_slice($json, 0, 2, true));
                    echo "</pre></div>";
                } else { echo "<p style='color:red'>Erreur " . $res['code'] . "</p>"; }
            ?>
        </div>
    <?php endforeach; ?>

</body>
</html>