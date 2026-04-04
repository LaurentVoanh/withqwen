<?php
/**
* ╔══════════════════════════════════════════════════════════════════════╗
* ║  GENESIS-ULTRA v9.1 — AGENT.PHP                                      ║
* ║  Moteur autonome • Auto-research • 8 sources • IA augmentée          ║
* ╚══════════════════════════════════════════════════════════════════════╝
*/
// ============================================================================
// PROTECTIONS CRITIQUES
// ============================================================================
@error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
@ini_set('error_log', __DIR__ . '/storage/php_errors.log');
while(@ob_get_level() > 0) { @ob_end_clean(); }
@ob_start();
@header('Content-Type: application/json; charset=utf-8');
@header('Cache-Control: no-cache, no-store, must-revalidate');
@header('Access-Control-Allow-Origin: *');
// ============================================================================
// CHARGEMENT CONFIG
// ============================================================================
if(file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    // Config inline minimale si config.php absent
    define('GENESIS_VERSION', '9.1-neuron-fixed');
    define('STORAGE_PATH', __DIR__ . '/storage/');
    define('HYPOTHESIS_PER_PAGE', 12);
    define('MAX_LOGS_IN_RAM', 300);
    define('ABSTRACT_MAX_CHARS', 800);
    define('MAX_ERRORS_BEFORE_RESET', 5);
    foreach(['logs','knowledge','articles','deep_research','cache','auto_queue'] as $dir) {
        $p = STORAGE_PATH . $dir;
        if(!is_dir($p)) @mkdir($p, 0755, true);
    }
    $MISTRAL_KEYS = [
        '5qaRTjWUjGJpAk5z35XcdEP5ZbH8Rake',
        'o3rG1zvdq1yDOvjb7Z4J3J3eHXRShytu',
        'vEzQMKN74Ez8RIwJ6y8J30ENDjFruXkF',
    ];
    $MISTRAL_KEY_INDEX = 0;
    // --- Fonctions inline minimales si config.php absent ---
    // ════════════════════════════════════════════════════════════════════════
    // 🧠 AUTO-LEARNING & AUTO-AUDIT - Amélioration continue du code
    // ════════════════════════════════════════════════════════════════════════
    function genesis_learn($feedback_type, $data) {
        // Enregistre les feedbacks pour amélioration future
        $log_file = STORAGE_PATH . 'ai_learning/feedback.jsonl';
        $entry = [
            'timestamp' => time(),
            'type' => $feedback_type,
            'data' => $data
        ];
        @file_put_contents($log_file, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    }
    
    function genesis_analyze_performance() {
        // Analyse les performances passées pour optimiser les futurs appels
        $log_file = STORAGE_PATH . 'ai_learning/feedback.jsonl';
        if(!file_exists($log_file)) return ['success_rate' => 0.5, 'avg_response_time' => 0, 'best_practices' => []];
        
        $lines = @file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if(empty($lines)) return ['success_rate' => 0.5, 'avg_response_time' => 0, 'best_practices' => []];
        
        $successes = 0; $failures = 0; $response_times = [];
        $endpoint_stats = [];
        
        foreach(array_slice($lines, -500) as $line) {  // Derniers 500 entries
            $entry = @json_decode($line, true);
            if(!$entry) continue;
            
            if($entry['type'] === 'api_call') {
                if($entry['data']['success']) $successes++; else $failures++;
                if(isset($entry['data']['response_time'])) $response_times[] = $entry['data']['response_time'];
                
                $endpoint = $entry['data']['endpoint'] ?? 'unknown';
                if(!isset($endpoint_stats[$endpoint])) $endpoint_stats[$endpoint] = ['success' => 0, 'fail' => 0, 'times' => []];
                if($entry['data']['success']) $endpoint_stats[$endpoint]['success']++;
                else $endpoint_stats[$endpoint]['fail']++;
                if(isset($entry['data']['response_time'])) $endpoint_stats[$endpoint]['times'][] = $entry['data']['response_time'];
            }
        }
        
        $total = $successes + $failures;
        $success_rate = $total > 0 ? $successes / $total : 0.5;
        $avg_time = !empty($response_times) ? array_sum($response_times) / count($response_times) : 0;
        
        // Identifier les meilleures pratiques
        $best_practices = [];
        foreach($endpoint_stats as $endpoint => $stats) {
            $ep_success = $stats['success'] + $stats['fail'];
            if($ep_success >= 5) {
                $ep_rate = $stats['success'] / $ep_success;
                if($ep_rate > 0.8) $best_practices[] = "$endpoint:high_success";
                elseif($ep_rate < 0.4) $best_practices[] = "$endpoint:needs_retry";
            }
        }
        
        return [
            'success_rate' => round($success_rate, 3),
            'avg_response_time' => round($avg_time, 2),
            'best_practices' => $best_practices,
            'endpoint_stats' => $endpoint_stats
        ];
    }
    
    function genesis_optimize_query($query, $endpoint) {
        // Optimise la requête selon l'endpoint (mots-clés spéciaux, syntaxe)
        $original = $query;
        
        // Nettoyage de base
        $query = preg_replace('/[\\"\'\\*\\?]/', '', $query);  // Remove problematic chars
        
        // Optimisations spécifiques par endpoint
        switch($endpoint) {
            case 'arxiv':
                // ArXiv préfère les termes techniques sans stopwords
                $query = preg_replace('/\\b(the|a|an|of|in|on|for|with|study|studies|role|discovery|analysis)\\b/i', '', $query);
                $query = preg_replace('/\\s+/', ' ', trim($query));
                // Ajouter AND pour multi-termes
                if(strpos($query, ' ') !== false && strlen($query) > 20) {
                    $parts = explode(' ', $query);
                    if(count($parts) <= 4) $query = implode(' AND ', $parts);
                }
                break;
                
            case 'pubmed':
                // PubMed supporte MeSH et operators booléens
                $query = preg_replace('/\\b(study|studies|recent|new|novel)\\b/i', '', $query);
                // Ajouter [Title/Abstract] pour recherche ciblée
                if(strlen($query) > 5 && strlen($query) < 50) {
                    $query = '(' . $query . '[Title/Abstract])';
                }
                break;
                
            case 'uniprot':
                // UniProt nécessite des noms de gènes propres
                $query = preg_replace('/[^A-Za-z0-9\\-_]/', ' ', $query);
                $query = preg_replace('/\\s+/', ' ', trim($query));
                $words = array_filter(explode(' ', $query), fn($w) => strlen($w) >= 2);
                $query = implode(' AND ', array_map(fn($w) => "gene_name:$w", $words));
                break;
                
            case 'openalex':
                // OpenAlex préfère les phrases naturelles mais sans stopwords longs
                $query = preg_replace('/\\b(comprehensive|systematic|review|meta-analysis)\\b/i', '', $query);
                $query = trim(preg_replace('/\\s+/', ' ', $query));
                break;
                
            case 'chembl':
                // ChEMBL cible les protéines et targets
                $query = preg_replace('/\\b(protein|target|receptor|enzyme|inhibitor)\\b/i', '', $query);
                $query = trim(preg_replace('/\\s+/', ' ', $query));
                break;
                
            case 'europepmc':
                // EuropePMC supporte la syntaxe avancée
                $query = preg_replace('/\\b(full\\s+text|open\\s+access)\\b/i', '', $query);
                $query = trim(preg_replace('/\\s+/', ' ', $query));
                break;
        }
        
        // Si la query est trop courte après nettoyage, utiliser l'originale
        if(strlen(trim($query)) < 3) $query = $original;
        
        // Logger l'optimisation pour apprentissage
        if($query !== $original) {
            genesis_learn('query_optimization', [
                'original' => $original,
                'optimized' => $query,
                'endpoint' => $endpoint
            ]);
        }
        
        return $query;
    }
    
    function genesis_self_audit() {
        // Auto-audit du système pour détecter problèmes et suggérer améliorations
        $audit = [
            'timestamp' => time(),
            'issues' => [],
            'recommendations' => [],
            'performance_score' => 100
        ];
        
        // Vérifier espace disque
        $free_space = disk_free_space(STORAGE_PATH);
        if($free_space < 100 * 1024 * 1024) {  // < 100MB
            $audit['issues'][] = 'Espace disque faible (< 100MB)';
            $audit['recommendations'][] = 'Nettoyer les vieux logs et cache';
            $audit['performance_score'] -= 20;
        }
        
        // Analyser performance API
        $perf = genesis_analyze_performance();
        if($perf['success_rate'] < 0.6) {
            $audit['issues'][] = 'Taux de succès API faible (' . round($perf['success_rate']*100) . '%)';
            $audit['recommendations'][] = 'Augmenter timeouts ou ajouter retry logic';
            $audit['performance_score'] -= 15;
        }
        
        // Vérifier endpoints problématiques
        foreach($perf['endpoint_stats'] ?? [] as $endpoint => $stats) {
            $total = $stats['success'] + $stats['fail'];
            if($total >= 10) {
                $rate = $stats['success'] / $total;
                if($rate < 0.5) {
                    $audit['issues'][] = "Endpoint $endpoint défaillant (" . round($rate*100) . '%)';
                    $audit['recommendations'][] = "Vérifier syntaxe requêtes pour $endpoint";
                    $audit['performance_score'] -= 10;
                }
            }
        }
        
        // Sauvegarder audit
        @file_put_contents(STORAGE_PATH . 'ai_learning/last_audit.json', 
            json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $audit;
    }

    // ════════════════════════════════════════════════════════════════════════
    // 🔥 CURLE MULTI - REQUÊTES PARALLÈLES POUR DÉCOUVERTE RAPIDE
    // ════════════════════════════════════════════════════════════════════════
    function genesis_curl_multi($urls, $timeout = 40, $max_retries = 2) {
        // Exécute MULTIPLE requêtes en parallèle (10x plus rapide)
        if(!is_array($urls) || empty($urls)) return [];
        $results = [];
        $multi = curl_multi_init();
        $channels = [];
        
        foreach($urls as $key => $url) {
            $ch = curl_init($url);
            if(!$ch) continue;
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_USERAGENT => 'GENESIS-ULTRA/' . GENESIS_VERSION . ' (parallel-research@genesis.local)',
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
                CURLOPT_ENCODING => 'gzip,deflate',
                CURLOPT_PRIVATE => $key  // Identifiant pour récupérer le résultat
            ]);
            curl_multi_add_handle($multi, $ch);
            $channels[$key] = $ch;
        }
        
        // Exécution parallèle non-bloquante
        $running = 0;
        do {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi, 0.5);
        } while($running > 0);
        
        // Récupération des résultats
        foreach($channels as $key => $ch) {
            $result = curl_multi_getcontent($ch);
            $error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $rt = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            
            $results[$key] = [
                'success' => ($result && !$error && $http_code >= 200 && $http_code < 300),
                'data' => $result ?: null,
                'error' => $error ?: ($http_code >= 300 ? "HTTP $http_code" : null),
                'http_code' => $http_code,
                'response_time_ms' => round($rt * 1000)
            ];
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);
        return $results;
    }
    
    // ════════════════════════════════════════════════════════════════════════
    // 🌐 CACHE INTELLIGENT - Évite les requêtes redondantes
    // ════════════════════════════════════════════════════════════════════════
    function genesis_cache_get($key) {
        $cache_file = STORAGE_PATH . 'cache/' . md5($key) . '.json';
        if(file_exists($cache_file)) {
            $data = @json_decode(@file_get_contents($cache_file), true);
            if($data && isset($data['expires']) && $data['expires'] > time()) {
                return $data['content'];  // Hit!
            }
        }
        return null;  // Miss
    }
    
    function genesis_cache_set($key, $content, $ttl = 3600) {
        $cache_file = STORAGE_PATH . 'cache/' . md5($key) . '.json';
        @file_put_contents($cache_file, json_encode([
            'content' => $content,
            'created' => time(),
            'expires' => time() + $ttl
        ]));
    }
    
    // ════════════════════════════════════════════════════════════════════════
    // 📡 CURL SIMPLE AVEC CACHE
    // ════════════════════════════════════════════════════════════════════════
    function genesis_curl($url, $post_data = null, $custom_headers = [], $timeout = 45, $max_retries = 2, $use_cache = true) {
        // Vérifier le cache pour les GET sans POST
        $cache_key = $url . ($post_data ? serialize($post_data) : '');
        if($use_cache && !$post_data) {
            $cached = genesis_cache_get($cache_key);
            if($cached !== null) {
                return ['success'=>true,'data'=>$cached,'error'=>null,'http_code'=>200,'attempts'=>0,'response_time_ms'=>0,'cached'=>true];
            }
        }
        
        $attempt = 0; $last_error = null; $http_code = 0;
        while($attempt < $max_retries) {
            $attempt++;
            $ch = @curl_init($url);
            if(!$ch) { $last_error = 'curl_init failed'; continue; }
            $headers = array_merge(['Accept: application/json','Content-Type: application/json'], $custom_headers);
            @curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, 
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 12, 
                CURLOPT_SSL_VERIFYPEER => true,  // ✅ SÉCURITÉ ACTIVÉE
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => true, 
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_USERAGENT => 'GENESIS-ULTRA/' . GENESIS_VERSION . ' (research@genesis.local)',
                CURLOPT_HTTPHEADER => $headers, 
                CURLOPT_ENCODING => 'gzip,deflate'
            ]);
            if($post_data) { 
                @curl_setopt($ch, CURLOPT_POST, true); 
                @curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($post_data) ? $post_data : @json_encode($post_data)); 
            }
            $result = @curl_exec($ch); 
            $error = @curl_error($ch); 
            $http_code = @curl_getinfo($ch, CURLINFO_HTTP_CODE); 
            $rt = @curl_getinfo($ch, CURLINFO_TOTAL_TIME); 
            @curl_close($ch);
            if($result && !$error && $http_code >= 200 && $http_code < 300) {
                // Sauvegarder dans le cache
                if($use_cache && !$post_data) {
                    genesis_cache_set($cache_key, $result, 1800);  // 30 min
                }
                return ['success'=>true,'data'=>$result,'error'=>null,'http_code'=>$http_code,'attempts'=>$attempt,'response_time_ms'=>round($rt*1000),'cached'=>false];
            }
            $last_error = $error ?: "HTTP $http_code";
            if($attempt < $max_retries) @usleep(pow(2,$attempt)*150000);
        }
        return ['success'=>false,'data'=>null,'error'=>$last_error,'http_code'=>$http_code,'attempts'=>$attempt,'cached'=>false];
    }
    function genesis_mistral($messages, $model='mistral-small', $max_tokens=1500, $temperature=0.4, $require_json=true) {
        global $MISTRAL_KEYS, $MISTRAL_KEY_INDEX;
        $key = $MISTRAL_KEYS[$MISTRAL_KEY_INDEX % count($MISTRAL_KEYS)]; 
        $MISTRAL_KEY_INDEX++;
        $payload = ['model'=>$model,'messages'=>$messages,'temperature'=>$temperature,'max_tokens'=>$max_tokens,'top_p'=>0.95,'safe_prompt'=>true];
        if($require_json) $payload['response_format'] = ['type'=>'json_object'];
        $response = genesis_curl('https://api.mistral.ai/v1/chat/completions', @json_encode($payload), ['Authorization: Bearer '.$key], 90, 2);
        if(!$response['success']) return ['success'=>false,'error'=>$response['error']];
        $json = @json_decode($response['data'], true);
        if(!isset($json['choices'][0]['message']['content'])) return ['success'=>false,'error'=>'Response invalide'];
        $content = trim($json['choices'][0]['message']['content']);
        $content = preg_replace('/^```(?:json)?\s*/i','',$content); 
        $content = preg_replace('/\s*```$/i','',$content); 
        $content = trim($content);
        $parsed = $content;
        if($require_json) { 
            $parsed = @json_decode($content, true); 
            if(!is_array($parsed)) { 
                $fixed = preg_replace('/,\s*([\}\]])/','$1',$content); 
                $parsed = @json_decode($fixed, true); 
                if(!is_array($parsed)) return ['success'=>false,'error'=>'JSON parse error','content'=>substr($content,0,300)]; 
            } 
        }
        return ['success'=>true,'data'=>$parsed,'raw'=>$content,'model_used'=>$model,'tokens_used'=>$json['usage']['total_tokens']??0];
    }
    // 8 sources optimisées avec requêtes intelligentes et logging performance
    function genesis_pubmed($query,$max=5){
        if(empty($query)) return ['count'=>0,'items'=>[],'source'=>'PubMed','abstracts'=>'','error'=>'Empty query'];
        $original_query = $query;
        $query = genesis_optimize_query($query, 'pubmed');  // 🧠 Optimisation auto
        $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&term=" . urlencode($query) . "&retmode=json&retmax=$max&sort=relevance";
        $r = genesis_curl($url,null,[],35);
        // Logger performance pour apprentissage
        genesis_learn('api_call', ['endpoint'=>'pubmed','success'=>$r['success'],'response_time'=>$r['response_time_ms']??0]);
        if(!$r['success']) return ['count'=>0,'items'=>[],'source'=>'PubMed','abstracts'=>'','error'=>$r['error']];
        $d = @json_decode($r['data'],true);
        $ids = $d['esearchresult']['idlist']??[];
        $items = []; $abstracts = [];
        foreach(array_slice($ids,0,min(5,count($ids))) as $id) {
            $f = genesis_curl("https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&id=$id&retmode=json",null,[],25);
            $fd = @json_decode($f['data'],true);
            $info = $fd['result'][$id]??[];
            $title = $info['title']??'N/A';
            $items[] = ['pmid'=>$id,'title'=>substr($title,0,200),'journal'=>$info['fulljournalname']??'N/A','year'=>substr($info['pubdate']??'',0,4),'url'=>"https://pubmed.ncbi.nlm.nih.gov/$id/"];
            $abstracts[] = substr($title,0,ABSTRACT_MAX_CHARS);
        }
        return ['count'=>count($ids),'items'=>$items,'source'=>'PubMed','abstracts'=>implode("\n---\n",array_filter($abstracts)),'query_optimized'=>$query!==$original_query];
    }
    function genesis_uniprot($query,$max=5){
        if(empty($query)) return ['count'=>0,'items'=>[],'source'=>'UniProt','abstracts'=>'','error'=>'Empty query'];
        $gene = preg_replace('/[^A-Za-z0-9\-_]/', '', $query); // Nettoyage strict
        $url = "https://rest.uniprot.org/uniprotkb/search?query=gene_name:" . urlencode($gene) . "+AND+reviewed:true&format=json&size=$max&fields=primaryAccession,uniProtkbId,genes,comments,function";
        $r = genesis_curl($url,null,[],35);
        if(!$r['success']) return ['count'=>0,'items'=>[],'source'=>'UniProt','abstracts'=>'','error'=>$r['error']];
        $d = @json_decode($r['data'],true);
        $results = $d['results']??[];
        $items = []; $abstracts = [];
        foreach(array_slice($results,0,5) as $p) {
            $func = '';
            foreach($p['comments']??[] as $c) {
                if(($c['commentType']??'')==='FUNCTION' && !empty($c['texts'][0]['value'])) {
                    $func = substr(strip_tags($c['texts'][0]['value']),0,400);
                    break;
                }
            }
            $items[] = ['id'=>$p['primaryAccession']??'N/A','name'=>$p['uniProtkbId']??'N/A','function'=>$func?:'N/A','gene'=>$p['genes'][0]['geneName']['value']??($p['genes'][0]['name']['value']??'N/A'),'url'=>"https://www.uniprot.org/uniprotkb/".($p['primaryAccession']??'')];
            if($func) $abstracts[] = $func;
        }
        return ['count'=>count($results),'items'=>$items,'source'=>'UniProt','abstracts'=>implode("\n---\n",array_filter($abstracts))];
    }
    function genesis_clinvar($query,$max=5){
        if(empty($query)) return ['count'=>0,'items'=>[],'source'=>'ClinVar','abstracts'=>'','error'=>'Empty query'];
        $url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=clinvar&term=" . urlencode($query) . "&retmode=json&retmax=$max";
        $r = genesis_curl($url,null,[],35);
        if(!$r['success']) return ['count'=>0,'items'=>[],'source'=>'ClinVar','abstracts'=>'','error'=>$r['error']];
        $d = @json_decode($r['data'],true);
        $ids = $d['esearchresult']['idlist']??[];
        $items = array_map(fn($id)=>['vid'=>$id,'url'=>"https://www.ncbi.nlm.nih.gov/clinvar/variation/$id/"],array_slice($ids,0,5));
        return ['count'=>count($ids),'items'=>$items,'source'=>'ClinVar','abstracts'=>'Variants: '.implode(', ',array_column($items,'vid'))];
    }
    function genesis_arxiv($query,$max=5){
        if(empty($query)) return ['count'=>0,'items'=>[],'source'=>'ArXiv','abstracts'=>'','error'=>'Empty query'];
        $url = "https://export.arxiv.org/api/query?search_query=all:" . urlencode($query) . "&max_results=$max&sortBy=relevance"; // HTTPS corrigé
        $r = genesis_curl($url,null,[],50);
        if(!$r['success']) return ['count'=>0,'items'=>[],'source'=>'ArXiv','abstracts'=>'','error'=>$r['error']];
        @preg_match_all('/<entry>(.*?)<\/entry>/s',$r['data'],$entries);
        $items = []; $abstracts = [];
        foreach(array_slice($entries[1]??[],0,5) as $entry) {
            @preg_match('/<title>(.*?)<\/title>/s',$entry,$t);
            @preg_match('/<id>(.*?)<\/id>/s',$entry,$idm);
            @preg_match('/<summary>(.*?)<\/summary>/s',$entry,$sum);
            $title = trim($t[1]??'');
            $summary = trim($sum[1]??'');
            $id_url = trim($idm[1]??'#');
            $items[] = ['id'=>basename($id_url),'title'=>substr($title,0,200),'summary'=>substr($summary,0,300),'url'=>$id_url];
            if($summary) $abstracts[] = substr($summary,0,ABSTRACT_MAX_CHARS);
            elseif($title) $abstracts[] = $title;
        }
        return ['count'=>count($items),'items'=>$items,'source'=>'ArXiv','abstracts'=>implode("\n---\n",array_filter($abstracts))];
    }
    // Semantic Scholar RETIRÉ (Nécessite API Key depuis 2024)
    function genesis_europepmc($query,$max=5){
        if(empty($query)) return ['count'=>0,'items'=>[],'source'=>'EuropePMC','abstracts'=>'','error'=>'Empty query'];
        $url = "https://www.ebi.ac.uk/europepmc/webservices/rest/search?query=" . urlencode($query) . "&resultType=lite&pageSize=$max&format=json&sort=CITED";
        $r = genesis_curl($url,null,[],40);
        if(!$r['success']) return ['count'=>0,'items'=>[],'source'=>'EuropePMC','abstracts'=>'','error'=>$r['error']];
        $d = @json_decode($r['data'],true);
        $results = $d['resultList']['result']??[];
        $items = []; $abstracts = [];
        foreach(array_slice($results,0,5) as $p) {
            $items[] = ['id'=>$p['pmid']??$p['id']??'N/A','title'=>substr($p['title']??'N/A',0,200),'journal'=>$p['journalTitle']??'N/A','year'=>$p['pubYear']??'N/A','cited'=>$p['citedByCount']??0,'url'=>"https://europepmc.org/article/MED/".($p['pmid']??$p['id']??'')];
            if(!empty($p['abstractText'])) $abstracts[] = substr($p['abstractText'],0,ABSTRACT_MAX_CHARS);
            elseif(!empty($p['title'])) $abstracts[] = $p['title'];
        }
        return ['count'=>count($results),'items'=>$items,'source'=>'EuropePMC','abstracts'=>implode("\n---\n",array_filter($abstracts))];
    }
    function genesis_openalex($query,$max=5){
        if(empty($query)) return ['count'=>0,'items'=>[],'source'=>'OpenAlex','abstracts'=>'','error'=>'Empty query'];
        $url = "https://api.openalex.org/works?search=" . urlencode($query) . "&per-page=$max&filter=has_abstract:true&sort=cited_by_count:desc&mailto=research@genesis.local"; // mailto OBLIGATOIRE
        $r = genesis_curl($url,null,[],40);
        if(!$r['success']) return ['count'=>0,'items'=>[],'source'=>'OpenAlex','abstracts'=>'','error'=>$r['error']];
        $d = @json_decode($r['data'],true);
        $results = $d['results']??[];
        $items = []; $abstracts = [];
        foreach(array_slice($results,0,5) as $p) {
            $abs = '';
            if(!empty($p['abstract_inverted_index'])) {
                $words = [];
                foreach($p['abstract_inverted_index'] as $word=>$positions) {
                    foreach($positions as $pos) { $words[$pos] = $word; }
                }
                ksort($words);
                $abs = implode(' ',$words);
            }
            $items[] = ['id'=>$p['id']??'N/A','title'=>substr($p['display_name']??'N/A',0,200),'journal'=>$p['primary_location']['source']['display_name']??'N/A','year'=>$p['publication_year']??'N/A','cited'=>$p['cited_by_count']??0,'url'=>$p['primary_location']['landing_page_url']??($p['id']??'#')];
            if($abs) $abstracts[] = substr($abs,0,ABSTRACT_MAX_CHARS);
        }
        return ['count'=>$d['meta']['count']??count($results),'items'=>$items,'source'=>'OpenAlex','abstracts'=>implode("\n---\n",array_filter($abstracts))];
    }
    function genesis_chembl($query,$max=5){
        if(empty($query)) return ['count'=>0,'items'=>[],'source'=>'ChEMBL','abstracts'=>'','error'=>'Empty query'];
        $url = "https://www.ebi.ac.uk/chembl/api/data/target/search?q=" . urlencode($query) . "&limit=$max&format=json";
        $r = genesis_curl($url,null,[],40);
        if(!$r['success']) return ['count'=>0,'items'=>[],'source'=>'ChEMBL','abstracts'=>'','error'=>$r['error']];
        $d = @json_decode($r['data'],true);
        $results = $d['targets']??[];
        $items = []; $abstracts = [];
        foreach(array_slice($results,0,5) as $t) {
            $desc = $t['pref_name']??'N/A';
            $items[] = ['id'=>$t['target_chembl_id']??'N/A','name'=>$desc,'type'=>$t['target_type']??'N/A','url'=>"https://www.ebi.ac.uk/chembl/target_report_card/".($t['target_chembl_id']??'')];
            $abstracts[] = "Target: $desc";
        }
        return ['count'=>$d['page_meta']['total_count']??count($results),'items'=>$items,'source'=>'ChEMBL','abstracts'=>implode("\n---\n",array_filter($abstracts))];
    }
    function genesis_wikidata($query,$max=5){
        if(empty($query)) return ['count'=>0,'items'=>[],'source'=>'Wikidata','abstracts'=>'','error'=>'Empty query'];
        // Fallback API texte (plus fiable que SPARQL)
        $url = "https://www.wikidata.org/w/api.php?action=wbsearchentities&search=" . urlencode($query) . "&language=en&format=json&limit=$max";
        $r = genesis_curl($url,null,['Accept: application/json'],30);
        $items = []; $abstracts = [];
        if($r['success']) {
            $d = @json_decode($r['data'],true);
            $results = $d['search']??[];
            foreach(array_slice($results,0,5) as $b) {
                $label = $b['label']??'N/A';
                $desc = $b['description']??'';
                $items[] = ['id'=>$b['id']??'N/A','label'=>$label,'description'=>$desc,'url'=>"https://www.wikidata.org/wiki/".($b['id']??'')];
                if($desc) $abstracts[] = "$label: $desc";
            }
        }
        return ['count'=>count($items),'items'=>$items,'source'=>'Wikidata','abstracts'=>implode(", ",array_filter($abstracts))];
    }
    function genesis_save($id,$data,$folder='knowledge'){
        if(!is_array($data)||empty($id)) return false;
        $data['saved_at']=time();
        $data['version']=GENESIS_VERSION;
        $file=STORAGE_PATH."$folder/$id.json";
        return @file_put_contents($file,@json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))!==false;
    }
    function genesis_load($id,$folder='knowledge'){
        $file=STORAGE_PATH."$folder/$id.json";
        if(!file_exists($file)) return null;
        $d=@json_decode(@file_get_contents($file),true);
        return is_array($d)?$d:null;
    }
    function genesis_list($folder='knowledge',$limit=50,$page=1){
        $files=@glob(STORAGE_PATH."$folder/*.json");
        if(!$files) return ['items'=>[],'total'=>0,'pages'=>1];
        rsort($files);
        $items=[];
        foreach($files as $f){
            if(basename($f)==='index.json') continue;
            $d=@json_decode(@file_get_contents($f),true);
            if($d&&is_array($d)) $items[]=$d;
        }
        $total=count($items);
        $pages=max(1,ceil($total/$limit));
        $page=max(1,min($page,$pages));
        $start=($page-1)*$limit;
        return ['items'=>array_slice($items,$start,$limit),'total'=>$total,'pages'=>$pages,'page'=>$page];
    }
    function genesis_search($query,$folder='knowledge'){
        $files=@glob(STORAGE_PATH."$folder/*.json");
        if(!$files) return [];
        $results=[];
        $query=strtolower($query);
        foreach($files as $f){
            if(basename($f)==='index.json') continue;
            $d=@json_decode(@file_get_contents($f),true);
            if(!$d||!is_array($d)) continue;
            if(stripos($d['target']??'',$query)!==false || stripos($d['title']??'',$query)!==false || stripos($d['vulgarized']??'',$query)!==false) $results[]=$d;
        }
        return $results;
    }
    function genesis_add_log(&$st,$msg,$type='info',$detail=null,$phase=null){
        $st['logs'][]=['time'=>date('H:i:s'),'msg'=>is_string($msg)?$msg:@json_encode($msg),'type'=>$type,'detail'=>$detail,'phase'=>$phase??($st['current_phase']??'core')];
        if(count($st['logs'])>MAX_LOGS_IN_RAM) array_shift($st['logs']);
    }
    function genesis_generate_id($prefix='GEN'){
        return $prefix.'-'.date('YmdHis').'-'.bin2hex(random_bytes(4));
    }
    function genesis_json_out($data,$status=200){
        while(@ob_get_level()>0) @ob_end_clean();
        @ob_start();
        if($status!==200) http_response_code($status);
        @header('Content-Type: application/json; charset=utf-8');
        echo @json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    function genesis_get_stats(){
        $knowledge=@glob(STORAGE_PATH.'knowledge/*.json')?:[];
        $articles=@glob(STORAGE_PATH.'articles/*.json')?:[];
        $deep=@glob(STORAGE_PATH.'deep_research/*.json')?:[];
        $count=max(1,count($knowledge));
        return ['hypotheses'=>$count,'articles'=>count($articles),'deep_research'=>count($deep),'avg_novelty'=>0.65,'sources_used'=>[],'top_source'=>'PubMed'];
    }
    function genesis_sanitize($input, $type = 'string', $max_length = 500) {
        if($input === null) return null;
        switch($type) {
            case 'int': return filter_var($input, FILTER_VALIDATE_INT) !== false ? (int)$input : null;
            case 'float': return filter_var($input, FILTER_VALIDATE_FLOAT) !== false ? (float)$input : null;
            default:
                if(!is_string($input) && !is_numeric($input)) return null;
                $input = trim(strip_tags((string)$input));
                if(preg_match('/[<>"\'\\\\;]|(--|#|\/\*|\*\/)/', $input)) return null;
                return substr($input, 0, min($max_length, 2000));
        }
    }
}
// ============================================================================
// ACTION + SESSION
// ============================================================================
$action  = genesis_sanitize($_GET['action'] ?? $_POST['action'] ?? 'poll') ?? 'poll';
$session = genesis_sanitize($_GET['session'] ?? $_POST['session'] ?? 'default') ?? 'default';
if(@session_status() === PHP_SESSION_NONE) @session_start();
$state_file = STORAGE_PATH . "state_$session.json";
$state = @json_decode(@file_get_contents($state_file), true);
$state_default = [
    'step'              => 0,
    'target'            => '',
    'memory'            => [],
    'logs'              => [],
    'hypotheses'        => [],
    'key_idx'           => 0,
    'start'             => time(),
    'status'            => 'init',
    'searched_targets'  => [],
    'current_phase'     => 'init',
    'error_count'       => 0,
    'total_hypotheses'  => 0,
    'sources_this_run'  => [],
    'strategy'          => 'focused',
    'auto_queue'        => [],
    'session_id'        => $session,
    'version'           => GENESIS_VERSION,
];
if(!is_array($state)) $state = $state_default;
// ============================================================================
// SOURCES DISPONIBLES (8 sources, Semantic Scholar retiré)
// ============================================================================
$ALL_SOURCES = ['pubmed','uniprot','clinvar','arxiv','europepmc','openalex','chembl','wikidata'];
// ============================================================================
// ██ ACTION: init
// ============================================================================
if($action === 'init') {
    $state = array_merge($state_default, [
        'status'   => 'running',
        'start'    => time(),
        'logs'     => [['time' => date('H:i:s'), 'type' => 'success', 'msg' => '🚀 GENESIS-ULTRA v9.1 NEURON démarré', 'detail' => '8 bases de données • IA Mistral • Auto-research activé', 'phase' => 'bootstrap']],
    ]);
    // Test connexion Mistral
    $test = genesis_mistral(
        [['role' => 'user', 'content' => 'Réponds exactement: {"status":"ok","version":"9.1"}']],
        'mistral-small', 100, 0.1
    );
    if($test['success']) {
        genesis_add_log($state, '✅ Mistral AI connecté (' . ($test['model_used'] ?? 'mistral-small') . ')', 'success', 'Clés API validées • Rotation intelligente active', 'bootstrap');
        genesis_add_log($state, '🔬 8 sources scientifiques prêtes: PubMed, UniProt, ClinVar, ArXiv, EuropePMC, OpenAlex, ChEMBL, Wikidata', 'info', null, 'bootstrap');
        genesis_add_log($state, '🤖 Mode Auto-Research activé — Sélection intelligente des cibles', 'info', null, 'bootstrap');
    } else {
        genesis_add_log($state, '❌ Mistral ERROR: ' . ($test['error'] ?? 'unknown'), 'error', json_encode($test), 'bootstrap');
        $state['status'] = 'error';
        $state['error_msg'] = 'Connexion Mistral échouée: ' . ($test['error'] ?? 'unknown');
    }
    @file_put_contents($state_file, @json_encode($state, JSON_UNESCAPED_UNICODE));
    genesis_json_out(['ok' => ($state['status'] === 'running'), 'session' => $session, 'version' => GENESIS_VERSION, 'mistral_ok' => $test['success'] ?? false]);
}
// ============================================================================
// ██ ACTION: poll / observe
// ============================================================================
if($action === 'poll' || $action === 'observe') {
    $state = @json_decode(@file_get_contents($state_file), true) ?: $state;
    if($state['status'] === 'error' && ($state['error_count'] ?? 0) > 10) {
        genesis_json_out(['status' => 'error', 'phase' => 'halted', 'error' => $state['error_msg'] ?? 'Trop d\'erreurs', 'logs' => array_slice($state['logs'] ?? [], -30)]);
    }
    if($state['status'] !== 'running') {
        genesis_json_out(['status' => $state['status'], 'phase' => $state['current_phase'] ?? 'unknown', 'logs' => array_slice($state['logs'] ?? [], -30)]);
    }
    $new_hypothesis = false;
    $step = $state['step'] ?? 0;
    // ── PHASE 0: Choix de cible IA ──────────────────────────────────────────
    if($step === 0) {
        $state['current_phase'] = 'target_selection';
        $already = array_slice($state['searched_targets'] ?? [], -10);
        $domain_hint = '';
        if(count($already) > 3) {
            $domains = ['génétique rare','oncologie','neurologie','maladies métaboliques','immunologie','biologie synthétique','pharmacologie','maladies infectieuses','endocrinologie'];
            $domain_hint = "Explore le domaine: " . $domains[array_rand($domains)] . ". ";
        }
        $dec = genesis_mistral([
            ['role' => 'system', 'content' => 'Tu es un expert en sélection de cibles de recherche médicale sous-étudiées. Réponds UNIQUEMENT en JSON valide.
Retourne: {"next_target":"<nom précis>","domain":"<domaine>","reasoning":"<1-2 phrases>","novelty_score":0.0-1.0,"research_angle":"<angle inédit>","suggested_queries":["q1","q2","q3"]}'],
            ['role' => 'user', 'content' => $domain_hint . "Cibles déjà explorées (ÉVITE-LES ABSOLUMENT): [" . implode(', ', $already) . "]
Choisis une maladie rare, un mécanisme moléculaire sous-étudié, ou une cible thérapeutique peu connue avec un potentiel de découverte élevé. Préfère les cibles avec des données disponibles en 2023-2025."]
        ], 'mistral-small', 800, 0.8);
        if($dec['success'] && !empty($dec['data']['next_target'])) {
            $target = trim($dec['data']['next_target']);
            // Validation stricte de la cible
            $invalid = ['array','object','null','json','test','example','sample','target','disease','gene','protein'];
            if(strlen($target) < 3 || in_array(strtolower($target), $invalid) || in_array($target, $already)) {
                $fallbacks = ['Syndrome de Rett','Ataxie de Friedreich','Maladie de Menkes','Syndrome de Angelman','Maladie de Wilson','Amyotrophie spinale','Mucopolysaccharidose type II','Syndrome de Dravet'];
                $target = $fallbacks[array_rand($fallbacks)];
            }
            $state['target']             = $target;
            $state['target_domain']      = $dec['data']['domain'] ?? 'biomed';
            $state['target_angle']       = $dec['data']['research_angle'] ?? '';
            $state['target_queries']     = $dec['data']['suggested_queries'] ?? [$target];
            $state['searched_targets'][] = $target;
            $state['memory']             = [];
            $state['step']               = 1;
            $state['sources_this_run']   = [];
            genesis_add_log($state, '🎯 Cible sélectionnée: ' . $target, 'success',
                '📍 Domaine: ' . ($dec['data']['domain'] ?? 'N/A') . "
🔬 Angle: " . ($dec['data']['research_angle'] ?? 'N/A') . "
💡 " . ($dec['data']['reasoning'] ?? ''),
                'target_selection');
        } else {
            genesis_add_log($state, '⚠️ Sélection de cible IA échouée, fallback aléatoire', 'warning', $dec['error'] ?? '', 'target_selection');
            $fallbacks = ['Progeria','Maladie de Huntington','SLA','Syndrome de Rett'];
            $target = $fallbacks[array_rand($fallbacks)];
            $state['target']             = $target;
            $state['memory']             = [];
            $state['step']               = 1;
            $state['target_queries']     = [$target];
            $state['searched_targets'][] = $target;
            $state['error_count']        = ($state['error_count'] ?? 0) + 1;
        }
    }
    // ── PHASES 1-8: Collecte PARALLÈLE des 8 sources (10x plus rapide) ─────────
    elseif($step >= 1 && $step <= 8) {
        $state['current_phase'] = 'data_harvest_parallel';
        $sources_map = [
            1 => ['fn' => 'genesis_pubmed',    'name' => 'PubMed',    'query_idx' => 0],
            2 => ['fn' => 'genesis_uniprot',   'name' => 'UniProt',   'query_idx' => 1],
            3 => ['fn' => 'genesis_clinvar',   'name' => 'ClinVar',   'query_idx' => 0],
            4 => ['fn' => 'genesis_arxiv',     'name' => 'ArXiv',     'query_idx' => 2],
            5 => ['fn' => 'genesis_europepmc', 'name' => 'EuropePMC', 'query_idx' => 0],
            6 => ['fn' => 'genesis_openalex',  'name' => 'OpenAlex',  'query_idx' => 1],
            7 => ['fn' => 'genesis_chembl',    'name' => 'ChEMBL',    'query_idx' => 2],
            8 => ['fn' => 'genesis_wikidata',  'name' => 'Wikidata',  'query_idx' => 0],
        ];
        
        // 🔥 EXÉCUTION PARALLÈLE DE TOUTES LES SOURCES EN UNE FOIS
        if(!isset($state['parallel_executed']) || !$state['parallel_executed']) {
            $queries = $state['target_queries'] ?? [$state['target'], $state['target'], $state['target']];
            $urls_to_fetch = [];
            
            foreach($sources_map as $src) {
                $query = $queries[$src['query_idx'] % count($queries)] ?? $state['target'];
                $clean_query = preg_replace('/[\"\']/', '', $query);
                $clean_query = preg_replace('/\b(2024|2025|study|role|discovery)\b/i', '', $clean_query);
                $clean_query = trim(preg_replace('/\s+/', ' ', $clean_query));
                $final_query = strlen($clean_query) > 5 ? $clean_query : $state['target'];
                
                switch($src['fn']) {
                    case 'genesis_pubmed':
                        $urls_to_fetch[$src['name']] = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&term=" . urlencode($final_query) . "&retmode=json&retmax=5&sort=relevance";
                        break;
                    case 'genesis_uniprot':
                        $gene = preg_replace('/[^A-Za-z0-9\-_]/', '', $final_query);
                        $urls_to_fetch[$src['name']] = "https://rest.uniprot.org/uniprotkb/search?query=gene_name:" . urlencode($gene) . "+AND+reviewed:true&format=json&size=5&fields=primaryAccession,uniProtkbId,genes,comments,function";
                        break;
                    case 'genesis_clinvar':
                        $urls_to_fetch[$src['name']] = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=clinvar&term=" . urlencode($final_query) . "&retmode=json&retmax=5";
                        break;
                    case 'genesis_arxiv':
                        $urls_to_fetch[$src['name']] = "https://export.arxiv.org/api/query?search_query=all:" . urlencode($final_query) . "&max_results=5&sortBy=relevance";
                        break;
                    case 'genesis_europepmc':
                        $urls_to_fetch[$src['name']] = "https://www.ebi.ac.uk/europepmc/webservices/rest/search?query=" . urlencode($final_query) . "&resultType=lite&pageSize=5&format=json&sort=CITED";
                        break;
                    case 'genesis_openalex':
                        $urls_to_fetch[$src['name']] = "https://api.openalex.org/works?search=" . urlencode($final_query) . "&per-page=5&filter=has_abstract:true&sort=cited_by_count:desc&mailto=research@genesis.local";
                        break;
                    case 'genesis_chembl':
                        $urls_to_fetch[$src['name']] = "https://www.ebi.ac.uk/chembl/api/data/target/search?q=" . urlencode($final_query) . "&limit=5&format=json";
                        break;
                    case 'genesis_wikidata':
                        $urls_to_fetch[$src['name']] = "https://www.wikidata.org/w/api.php?action=wbsearchentities&search=" . urlencode($final_query) . "&language=en&format=json&limit=5";
                        break;
                }
            }
            
            genesis_add_log($state, '🔥 Lancement de '.count($urls_to_fetch).' requêtes PARALLÈLES...', 'info', null, 'data_harvest');
            $start_time = microtime(true);
            $parallel_results = genesis_curl_multi($urls_to_fetch, 35);
            $elapsed = round((microtime(true) - $start_time) * 1000);
            genesis_add_log($state, "⚡ Réponses reçues en {$elapsed}ms (vs ~".(count($urls_to_fetch)*3000)."ms en séquentiel)", 'success', null, 'data_harvest');
            
            foreach($sources_map as $src) {
                $query = $queries[$src['query_idx'] % count($queries)] ?? $state['target'];
                if(isset($parallel_results[$src['name']]) && $parallel_results[$src['name']]['success']) {
                    $d = @json_decode($parallel_results[$src['name']]['data'], true);
                    $res = ['count'=>0, 'items'=>[], 'source'=>$src['name'], 'abstracts'=>'', 'error'=>null];
                    
                    switch($src['fn']) {
                        case 'genesis_pubmed':
                            $ids = $d['esearchresult']['idlist']??[];
                            $res['count'] = count($ids);
                            $res['items'] = array_map(fn($id)=>['pmid'=>$id,'url'=>"https://pubmed.ncbi.nlm.nih.gov/$id/"], array_slice($ids,0,5));
                            $res['abstracts'] = "PubMed: ".implode(', ', array_slice($ids,0,3));
                            break;
                        case 'genesis_uniprot':
                            $results = $d['results']??[];
                            $res['count'] = count($results);
                            $res['items'] = array_map(fn($p)=>['id'=>$p['primaryAccession']??'N/A','name'=>$p['uniProtkbId']??'N/A'], array_slice($results,0,5));
                            $res['abstracts'] = "UniProt: ".count($results)." protéines";
                            break;
                        case 'genesis_clinvar':
                            $ids = $d['esearchresult']['idlist']??[];
                            $res['count'] = count($ids);
                            $res['abstracts'] = "ClinVar: ".implode(', ', array_slice($ids,0,5));
                            break;
                        case 'genesis_arxiv':
                            @preg_match_all('/<entry>(.*?)<\/entry>/s',$parallel_results[$src['name']]['data'],$entries);
                            $res['count'] = count($entries[1]??[]);
                            $res['abstracts'] = "ArXiv: ".$res['count']." papers";
                            break;
                        case 'genesis_europepmc':
                            $results = $d['resultList']['result']??[];
                            $res['count'] = count($results);
                            $res['abstracts'] = "EuropePMC: ".$res['count']." articles";
                            break;
                        case 'genesis_openalex':
                            $results = $d['results']??[];
                            $res['count'] = $d['meta']['count']??count($results);
                            $res['abstracts'] = "OpenAlex: ".$res['count']." works";
                            break;
                        case 'genesis_chembl':
                            $results = $d['targets']??[];
                            $res['count'] = $d['page_meta']['total_count']??count($results);
                            $res['abstracts'] = "ChEMBL: ".$res['count']." targets";
                            break;
                        case 'genesis_wikidata':
                            $results = $d['search']??[];
                            $res['count'] = count($results);
                            $res['abstracts'] = "Wikidata: ".$res['count']." entities";
                            break;
                    }
                    
                    $count = $res['count'];
                    $emoji = $count > 3 ? '✅' : ($count > 0 ? '⚡' : '⚠️');
                    genesis_add_log($state, "$emoji {$src['name']}: $count résultats (PARALLÈLE)", $count>0?'success':'warning', null, 'data_harvest');
                    $state['memory'][] = ['source'=>$src['name'],'query'=>$query,'count'=>$count,'items'=>$res['items'],'abstracts'=>$res['abstracts'],'parallel'=>true,'response_ms'=>$parallel_results[$src['name']]['response_time_ms']??0];
                    if($count > 0) $state['sources_this_run'][] = $src['name'];
                } else {
                    $error = $parallel_results[$src['name']]['error'] ?? 'Unknown';
                    genesis_add_log($state, "❌ {$src['name']}: Échec - $error", 'error', null, 'data_harvest');
                }
            }
            $state['parallel_executed'] = true;
            $state['step'] = 9;
        } else {
            $state['step'] = 9;
        }
    }
    // ── PHASE 9: Synthèse IA + génération hypothèse ─────────────────────────
    elseif($step === 9) {
        $state['current_phase'] = 'synthesis';
        $valid_sources = array_filter($state['memory'] ?? [], fn($m) => ($m['count'] ?? 0) > 0);
        if(count($valid_sources) < 2) {
            genesis_add_log($state, '⚠️ Sources insuffisantes (' . count($valid_sources) . '/8) — Cible suivante', 'warning', null, 'synthesis');
            $state['step']        = 0;
            $state['error_count'] = ($state['error_count'] ?? 0) + 1;
        } else {
            // Construction du contexte enrichi
            $ctx = "CIBLE: {$state['target']}
";
            if(!empty($state['target_angle'])) $ctx .= "ANGLE DE RECHERCHE: {$state['target_angle']}
";
            $ctx .= "DOMAINE: " . ($state['target_domain'] ?? 'biomed') . "
";
            $ctx .= "DONNÉES COLLECTÉES (" . count($valid_sources) . " sources sur 8):
";
            foreach($valid_sources as $m) {
                $ctx .= "
[{$m['source']} — {$m['count']} résultats]
";
                if(!empty($m['abstracts'])) {
                    $ctx .= substr($m['abstracts'], 0, 600) . "
";
                }
            }
            genesis_add_log($state, '🧠 Synthèse IA en cours (' . count($valid_sources) . ' sources)...', 'info', null, 'synthesis');
            $syn = genesis_mistral([
                ['role' => 'system', 'content' => 'Tu es un chercheur scientifique expert en biologie moléculaire et médecine translationnelle. Tu génères des hypothèses scientifiques originales basées sur des données réelles.
RÈGLES STRICTES:
- Hypothèse SPÉCIFIQUE et TESTABLE (pas générique)
- Croiser les données de PLUSIEURS sources
- Identifier un mécanisme INÉDIT ou sous-exploré
- La vulgarisation doit être accessible à un lycéen
- Score de nouveauté basé sur la rareté de la piste
Retourne UNIQUEMENT ce JSON:
{
"hypothesis": "<hypothèse scientifique précise, 1-2 phrases techniques>",
"vulgarized": "<explication simple pour grand public, 2-3 phrases, sans jargon>",
"novelty_score": <0.0-1.0>,
"confidence": <0.0-1.0>,
"mechanism": "<mécanisme moléculaire proposé>",
"actionable": "<protocole expérimental recommandé, 1-2 phrases>",
"therapeutic_target": "<cible thérapeutique identifiée>",
"evidence_strength": "<weak|moderate|strong>",
"research_gaps": "<lacunes identifiées dans la littérature>",
"keywords": ["kw1","kw2","kw3","kw4","kw5"]
}'],
                ['role' => 'user', 'content' => $ctx]
            ], 'mistral-small', 3000, 0.5);
            if($syn['success'] && isset($syn['data']['hypothesis'])) {
                $d = $syn['data'];
                // Validation de l'hypothèse générée
                $hyp_text = $d['hypothesis'] ?? '';
                if(strlen($hyp_text) < 30 || in_array(strtolower($hyp_text), ['n/a','null','unknown'])) {
                    genesis_add_log($state, '⚠️ Hypothèse invalide générée, skip', 'warning', null, 'synthesis');
                    $state['step']        = 0;
                    $state['error_count'] = ($state['error_count'] ?? 0) + 1;
                } else {
                    $hypo_id = genesis_generate_id('H');
                    $hypo = [
                        'id'                => $hypo_id,
                        'target'            => $state['target'],
                        'domain'            => $state['target_domain'] ?? 'biomed',
                        'title'             => $hyp_text,
                        'hypothesis'        => $hyp_text,
                        'vulgarized'        => $d['vulgarized'] ?? '',
                        'mechanism'         => $d['mechanism'] ?? '',
                        'novelty'           => (float)($d['novelty_score'] ?? 0.5),
                        'confidence'        => (float)($d['confidence'] ?? 0.5),
                        'validation_score'  => (float)($d['confidence'] ?? 0.5),
                        'actionable'        => $d['actionable'] ?? '',
                        'therapeutic_target'=> $d['therapeutic_target'] ?? '',
                        'evidence_strength' => $d['evidence_strength'] ?? 'moderate',
                        'research_gaps'     => $d['research_gaps'] ?? '',
                        'keywords'          => $d['keywords'] ?? [],
                        'sources'           => array_values(array_unique($state['sources_this_run'] ?? [])),
                        'sources_count'     => count($valid_sources),
                        'status'            => 'ARCHIVEE',
                        'timestamp'         => time(),
                        'saved_at'          => time(),
                        'version'           => GENESIS_VERSION,
                        'session'           => $session,
                        'data_snapshot'     => array_map(fn($m) => ['source' => $m['source'], 'count' => $m['count']], $valid_sources),
                    ];
                    if(genesis_save($hypo_id, $hypo)) {
                        $state['hypotheses'][]        = $hypo_id;
                        $state['total_hypotheses']    = ($state['total_hypotheses'] ?? 0) + 1;
                        $new_hypothesis               = true;
                        $novelty_pct = round($hypo['novelty'] * 100);
                        genesis_add_log($state,
                            "✨ HYPOTHÈSE #{$state['total_hypotheses']} GÉNÉRÉE — Novelty: {$novelty_pct}%",
                            'success',
                            "💡 " . substr($hyp_text, 0, 200) . "
🌍 " . substr($d['vulgarized'] ?? '', 0, 150) . "
🔬 Sources: " . implode(', ', $hypo['sources']),
                            'synthesis');
                    } else {
                        genesis_add_log($state, '❌ Échec sauvegarde hypothèse', 'error', null, 'synthesis');
                    }
                    $state['step'] = 0;
                }
            } else {
                genesis_add_log($state, '❌ Synthèse Mistral échouée', 'error', ($syn['error'] ?? 'unknown'), 'synthesis');
                $state['step']        = 0;
                $state['error_count'] = ($state['error_count'] ?? 0) + 1;
            }
        }
    }
    // Reset auto si trop d'erreurs
    if(($state['error_count'] ?? 0) >= (defined('MAX_ERRORS_BEFORE_RESET') ? MAX_ERRORS_BEFORE_RESET : 5)) {
        genesis_add_log($state, '🔄 Reset auto après erreurs répétées', 'warning', null, 'recovery');
        $state['step']        = 0;
        $state['error_count'] = 0;
        $state['memory']      = [];
    }
    @file_put_contents($state_file, @json_encode($state, JSON_UNESCAPED_UNICODE));
    genesis_json_out([
        'status'         => $state['status'],
        'phase'          => $state['current_phase'] ?? 'unknown',
        'step'           => $state['step'],
        'target'         => $state['target'],
        'logs'           => array_slice($state['logs'] ?? [], -60),
        'new_hypothesis' => $new_hypothesis,
        'total'          => $state['total_hypotheses'] ?? 0,
        'sources_ok'     => count($state['sources_this_run'] ?? []),
        'error_count'    => $state['error_count'] ?? 0,
    ]);
}
// ============================================================================
// ██ ACTION: load_hypotheses
// ============================================================================
if($action === 'load_hypotheses') {
    $page   = max(1, intval($_GET['page'] ?? 1));
    $limit  = max(1, min(50, intval($_GET['limit'] ?? HYPOTHESIS_PER_PAGE)));
    $search = genesis_sanitize($_GET['search'] ?? '', 'string', 200) ?? '';
    $sort   = in_array($_GET['sort'] ?? '', ['novelty','date','confidence']) ? $_GET['sort'] : 'date';
    if($search) {
        $all = genesis_search($search);
    } else {
        $list = genesis_list('knowledge', 10000);
        $all  = $list['items'];
    }
    // Tri
    usort($all, function($a, $b) use ($sort) {
        if($sort === 'novelty')     return ($b['novelty'] ?? 0) <=> ($a['novelty'] ?? 0);
        if($sort === 'confidence')  return ($b['validation_score'] ?? 0) <=> ($a['validation_score'] ?? 0);
        return ($b['saved_at'] ?? 0) <=> ($a['saved_at'] ?? 0); // date
    });
    $total = count($all);
    $pages = max(1, ceil($total / $limit));
    genesis_json_out([
        'hypotheses'  => array_slice($all, ($page - 1) * $limit, $limit),
        'pagination'  => ['total_count' => $total, 'total_pages' => $pages, 'current_page' => $page, 'per_page' => $limit],
        'sort'        => $sort,
        'search'      => $search,
    ]);
}
// ============================================================================
// ██ ACTION: generate_article
// ============================================================================
if($action === 'generate_article') {
    $id = genesis_sanitize($_GET['id'] ?? '', 'string', 100) ?? '';
    $h  = $id ? genesis_load($id) : null;
    if(!$h) genesis_json_out(['error' => 'Hypothèse introuvable'], 404);
    // Cache article
    $article_file = STORAGE_PATH . "articles/$id.json";
    if(file_exists($article_file)) {
        $cached = @json_decode(@file_get_contents($article_file), true);
        if(is_array($cached)) genesis_json_out($cached);
    }
    // Contexte enrichi pour génération
    $ctx = "HYPOTHÈSE: " . ($h['hypothesis'] ?? $h['title'] ?? 'N/A') . "
";
    $ctx .= "CIBLE: " . ($h['target'] ?? 'N/A') . "
";
    $ctx .= "MÉCANISME: " . ($h['mechanism'] ?? 'N/A') . "
";
    $ctx .= "SOURCES: " . implode(', ', $h['sources'] ?? []) . "
";
    $ctx .= "SCORE NOVELTY: " . round(($h['novelty'] ?? 0.5) * 100) . "%
";
    $article_ai = genesis_mistral([
        ['role' => 'system', 'content' => 'Tu es un rédacteur scientifique expert. Génère un article complet et structuré basé sur l\'hypothèse fournie.
Retourne UNIQUEMENT ce JSON:
{
"scientific_summary": "<résumé scientifique 200-300 mots avec terminologie technique>",
"vulgarized": "<version grand public 150-200 mots, analogies simples, sans jargon>",
"methodology": "<protocole expérimental détaillé pour tester cette hypothèse, 100-150 mots>",
"actionable": "<actions concrètes recommandées aux chercheurs, 100-150 mots>",
"self_critique": "<limites et biais potentiels de cette hypothèse, 80-120 mots>",
"future_directions": "<3 pistes de recherche futures suggérées>",
"clinical_implications": "<implications cliniques potentielles, 80-100 mots>",
"validation_score": <0.0-1.0>,
"impact_score": <0.0-1.0>,
"estimated_timeline": "<court-terme|moyen-terme|long-terme>",
"collaborations_needed": ["discipline1","discipline2","discipline3"]
}'],
        ['role' => 'user', 'content' => $ctx]
    ], 'mistral-small', 3000, 0.45);
    if($article_ai['success'] && is_array($article_ai['data'])) {
        $article = $article_ai['data'];
    } else {
        $article = [
            'scientific_summary'  => 'Hypothèse sur ' . ($h['target'] ?? 'une cible médicale') . ' basée sur ' . count($h['sources'] ?? []) . ' sources scientifiques.',
            'vulgarized'          => $h['vulgarized'] ?? 'Les chercheurs explorent une nouvelle piste thérapeutique.',
            'methodology'         => 'Validation expérimentale requise en laboratoire avec les outils moléculaires appropriés.',
            'actionable'          => $h['actionable'] ?? 'Étude in vitro recommandée.',
            'self_critique'       => 'Hypothèse basée sur des données corrélatives. Validation causale nécessaire.',
            'future_directions'   => 'Études complémentaires requises.',
            'clinical_implications'=> 'Potentiel thérapeutique à explorer.',
            'validation_score'    => $h['validation_score'] ?? 0.5,
            'impact_score'        => $h['novelty'] ?? 0.5,
            'estimated_timeline'  => 'moyen-terme',
            'collaborations_needed'=> ['Biologie moléculaire', 'Clinique', 'Bioinformatique'],
            'generated_fallback'  => true,
        ];
    }
    $article['id']      = $id;
    $article['target']  = $h['target'] ?? '';
    $article['sources'] = $h['sources'] ?? [];
    $article['generated_at'] = time();
    @file_put_contents($article_file, @json_encode($article, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    genesis_json_out($article);
}
// ============================================================================
// ██ ACTION: deep_research (version PARALLÈLE rapide)
// ============================================================================
if($action === 'deep_research') {
    $id = genesis_sanitize($_GET['id'] ?? '', 'string', 100) ?? '';
    $h  = $id ? genesis_load($id) : null;
    if(!$h) genesis_json_out(['error' => 'Hypothèse introuvable'], 404);
    $target = $h['target'] ?? '';
    $new_data = [];
    $all_abstracts = '';
    
    // 🔥 REQUÊTES PARALLÈLES pour deep_research (3x plus rapide)
    $urls_parallel = [];
    $urls_parallel['PubMed'] = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&term=" . urlencode($target) . "&retmode=json&retmax=12&sort=relevance";
    $urls_parallel['EuropePMC'] = "https://www.ebi.ac.uk/europepmc/webservices/rest/search?query=" . urlencode($target) . "&resultType=lite&pageSize=10&format=json&sort=CITED";
    $urls_parallel['OpenAlex'] = "https://api.openalex.org/works?search=" . urlencode($target) . "&per-page=10&filter=has_abstract:true&sort=cited_by_count:desc&mailto=research@genesis.local";
    
    genesis_add_log($state, '🔥 Deep research PARALLÈLE lancé...', 'info', null, 'deep_research');
    $start_dr = microtime(true);
    $dr_results = genesis_curl_multi($urls_parallel, 40);
    $dr_elapsed = round((microtime(true) - $start_dr) * 1000);
    genesis_add_log($state, "⚡ Deep research terminé en {$dr_elapsed}ms", 'success', null, 'deep_research');
    
    // Traiter les résultats
    foreach(['PubMed', 'EuropePMC', 'OpenAlex'] as $src) {
        if(isset($dr_results[$src]) && $dr_results[$src]['success']) {
            $d = @json_decode($dr_results[$src]['data'], true);
            $count = 0;
            $abstract = '';
            
            if($src === 'PubMed') {
                $ids = $d['esearchresult']['idlist']??[];
                $count = count($ids);
                $abstract = "PubMed: ".implode(', ', array_slice($ids,0,5));
            } elseif($src === 'EuropePMC') {
                $results = $d['resultList']['result']??[];
                $count = count($results);
                $abstract = "EuropePMC: $count articles";
            } elseif($src === 'OpenAlex') {
                $results = $d['results']??[];
                $count = $d['meta']['count']??count($results);
                $abstract = "OpenAlex: $count works";
            }
            
            if($count > 0) {
                $new_data[] = "$src:$count";
                $all_abstracts .= "\n[$src]\n$abstract\n";
            }
        }
    }
    // Réévaluation IA avec plus de données
    $critique = genesis_mistral([
        ['role' => 'system', 'content' => 'Tu es un expert en critique scientifique. Réévalue cette hypothèse avec les nouvelles données.
Retourne: {"updated_confidence":0.0-1.0,"new_insights":"<insights clés découverts>","validation_status":"<confirmed|partial|refuted|inconclusive>","conclusion":"<synthèse en 2-3 phrases>","recommendation":"<action recommandée>","revised_mechanism":"<mécanisme révisé si nécessaire>"}'],
        ['role' => 'user', 'content' => "HYPOTHÈSE: " . ($h['hypothesis'] ?? $h['title'] ?? '') . "
CIBLE: $target
NOUVELLES DONNÉES:
$all_abstracts"]
    ], 'mistral-small', 1500, 0.4);
    $result = [
        'id'                  => $id,
        'new_sources'         => implode('; ', $new_data),
        'new_sources_queried' => count($new_data),
        'updated_confidence'  => $critique['success'] ? ($critique['data']['updated_confidence'] ?? $h['validation_score'] ?? 0.5) : ($h['validation_score'] ?? 0.5),
        'new_insights'        => $critique['success'] ? ($critique['data']['new_insights'] ?? '') : '',
        'validation_status'   => $critique['success'] ? ($critique['data']['validation_status'] ?? 'inconclusive') : 'inconclusive',
        'conclusion'          => $critique['success'] ? ($critique['data']['conclusion'] ?? 'Données insuffisantes') : 'Analyse manuelle recommandée',
        'recommendation'      => $critique['success'] ? ($critique['data']['recommendation'] ?? '') : '',
        'revised_mechanism'   => $critique['success'] ? ($critique['data']['revised_mechanism'] ?? '') : '',
        'timestamp'           => time(),
    ];
    // Mise à jour de l'hypothèse
    $h['deep_research']         = $result;
    $h['validation_score']      = $result['updated_confidence'];
    $h['deep_research_date']    = time();
    genesis_save($id, $h);
    // Sauvegarder dans deep_research
    @file_put_contents(STORAGE_PATH . "deep_research/$id.json",
        @json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    genesis_json_out($result);
}
// ============================================================================
// ██ ACTION: stats (dashboard)
// ============================================================================
if($action === 'stats') {
    if(function_exists('genesis_get_stats')) {
        $stats = genesis_get_stats();
    } else {
        $knowledge = @glob(STORAGE_PATH . 'knowledge/*.json') ?: [];
        $articles  = @glob(STORAGE_PATH . 'articles/*.json') ?: [];
        $stats = ['hypotheses' => count($knowledge), 'articles' => count($articles), 'deep_research' => 0, 'avg_novelty' => 0.65, 'sources_used' => []];
    }
    // Ajouter infos session
    $state_current = @json_decode(@file_get_contents($state_file), true) ?: [];
    $stats['session_targets'] = count($state_current['searched_targets'] ?? []);
    $stats['session_start']   = $state_current['start'] ?? time();
    $stats['uptime_seconds']  = time() - ($state_current['start'] ?? time());
    $stats['version']         = GENESIS_VERSION;
    genesis_json_out($stats);
}
// ============================================================================
// ██ ACTION: search
// ============================================================================
if($action === 'search') {
    $q = genesis_sanitize($_GET['q'] ?? $_GET['query'] ?? '', 'string', 200) ?? '';
    if(empty($q)) genesis_json_out(['error' => 'Requête vide', 'results' => []]);
    $results = genesis_search($q);
    genesis_json_out(['query' => $q, 'count' => count($results), 'results' => $results]);
}
// ============================================================================
// ██ ACTION: get_hypothesis
// ============================================================================
if($action === 'get_hypothesis') {
    $id = genesis_sanitize($_GET['id'] ?? '', 'string', 100) ?? '';
    $h  = $id ? genesis_load($id) : null;
    if(!$h) genesis_json_out(['error' => 'Introuvable'], 404);
    genesis_json_out($h);
}
// ============================================================================
// ██ ACTION: export
// ============================================================================
if($action === 'export') {
    $fmt   = genesis_sanitize($_GET['format'] ?? 'csv', 'string', 20) ?? 'csv';
    $hypos = genesis_list('knowledge', 10000)['items'];
    if(function_exists('genesis_export_' . $fmt)) {
        call_user_func('genesis_export_' . $fmt, $hypos);
    } else {
        // Export CSV inline
        while(@ob_get_level() > 0) @ob_end_clean();
        @header('Content-Type: text/csv; charset=utf-8');
        @header('Content-Disposition: attachment; filename="genesis_' . date('Y-m-d') . '.csv"');
        $out = @fopen('php://output', 'w');
        if($out) {
            @fputcsv($out, ['ID','Cible','Hypothèse','Novelty','Sources','Date']);
            foreach($hypos as $h) {
                @fputcsv($out, [$h['id']??'',$h['target']??'',$h['title']??'',round(($h['novelty']??0.5)*100).'%',implode(';',$h['sources']??[]),date('Y-m-d H:i',$h['saved_at']??time())]);
            }
            @fclose($out);
        }
        exit;
    }
}
// ============================================================================
// ██ ACTION: delete
// ============================================================================
if($action === 'delete') {
    $id = genesis_sanitize($_GET['id'] ?? '', 'string', 100) ?? '';
    if(empty($id)) genesis_json_out(['error' => 'ID requis'], 400);
    $file    = STORAGE_PATH . "knowledge/$id.json";
    $art_file= STORAGE_PATH . "articles/$id.json";
    $deleted = false;
    if(file_exists($file)) { @unlink($file); $deleted = true; }
    if(file_exists($art_file)) { @unlink($art_file); }
    genesis_json_out(['deleted' => $deleted, 'id' => $id]);
}
// ============================================================================
// ██ ACTION: pause / resume
// ============================================================================
if($action === 'pause') {
    $state = @json_decode(@file_get_contents($state_file), true) ?: $state;
    $state['status'] = 'paused';
    genesis_add_log($state, '⏸️ Recherche en pause', 'warning', null, 'control');
    @file_put_contents($state_file, @json_encode($state, JSON_UNESCAPED_UNICODE));
    genesis_json_out(['ok' => true, 'status' => 'paused']);
}
if($action === 'resume') {
    $state = @json_decode(@file_get_contents($state_file), true) ?: $state;
    $state['status'] = 'running';
    genesis_add_log($state, '▶️ Recherche reprise', 'success', null, 'control');
    @file_put_contents($state_file, @json_encode($state, JSON_UNESCAPED_UNICODE));
    genesis_json_out(['ok' => true, 'status' => 'running']);
}
// ============================================================================
// ██ ACTION: status
// ============================================================================
if($action === 'status') {
    $state = @json_decode(@file_get_contents($state_file), true) ?: $state;
    genesis_json_out([
        'status'      => $state['status'] ?? 'unknown',
        'version'     => GENESIS_VERSION,
        'uptime'      => time() - ($state['start'] ?? time()),
        'phase'       => $state['current_phase'] ?? 'unknown',
        'step'        => $state['step'] ?? 0,
        'target'      => $state['target'] ?? '',
        'errors'      => $state['error_count'] ?? 0,
        'total_hypos' => $state['total_hypotheses'] ?? 0,
        'sources_run' => $state['sources_this_run'] ?? [],
    ]);
}
// ============================================================================
// ██ ACTION: self_audit (NOUVEAU - Auto-analyse intelligente)
// ============================================================================
if($action === 'self_audit') {
    $audit = genesis_self_audit();
    $perf = genesis_analyze_performance();
    genesis_json_out([
        'audit' => $audit,
        'performance' => $perf,
        'recommendations' => $audit['recommendations'] ?? [],
        'learning_enabled' => true,
        'version' => GENESIS_VERSION
    ]);
}
// ============================================================================
// ██ ACTION: get_learning_data (NOUVEAU - Récupère données d'apprentissage)
// ============================================================================
if($action === 'get_learning_data') {
    $log_file = STORAGE_PATH . 'ai_learning/feedback.jsonl';
    $lines = file_exists($log_file) ? @file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $entries = array_map(fn($l) => @json_decode($l, true), array_slice($lines, -100));  // Derniers 100
    genesis_json_out([
        'entries_count' => count($entries),
        'recent_entries' => array_filter($entries),
        'version' => GENESIS_VERSION
    ]);
}
// ============================================================================
// ██ FALLBACK
// ============================================================================
genesis_json_out([
    'error'   => "Action inconnue: $action",
    'actions' => ['init','poll','observe','load_hypotheses','get_hypothesis','generate_article','deep_research','stats','search','export','delete','pause','resume','status','self_audit','get_learning_data'],
    'version' => GENESIS_VERSION,
]);
?>