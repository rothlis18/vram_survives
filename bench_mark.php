<?php
/**
 * File: bench_mark.php
 * Location: C:\xampp\htdocs\AI workspace\
 * Version: 5.2 - STABLE VRAM PURGE (Verified Unload)
 * Description: Fixes the "Timeout after 300003 ms" errors by ensuring
 *              the model is fully unloaded before loading the next one.
 */

// ============================================================================
// SECTION 1: CONFIGURATION & CONFIGURABLE OPTIONS
// ============================================================================
define('OLLAMA_API_URL', 'http://localhost:11434/api/chat');
define('OLLAMA_TAGS_URL', 'http://localhost:11434/api/tags');
define('OLLAMA_PS_URL', 'http://localhost:11434/api/ps'); 
define('OUTPUT_FILE', __DIR__ . '/benchmark_results.csv');
define('OUTPUT_DIR', __DIR__ . '/benchmark_outputs');

// THE MASTER AD SERVER LINK OVERRIDE
$FORCE_AGGRESSIVE_CACHE_CLEAR = true; 

$SLEEP_BETWEEN_CASES = 2; // Settle time for the silicon context switch
$SLEEP_AFTER_UNLOAD  = 5;  // INCREASED FROM 2 TO 5 SECONDS for safety

// THE QUESTION
$BENCHMARK_QUESTION = "Provide an un-hedged, quantitative financial systems-engineering critique of the systemic risk profile and downstream liquidity vulnerabilities of an SEC-registered multi-billion dollar family office investment adviser—specifically referencing Cercano Management LLC in Bellevue, WA—regarding structural exposure to the private commercial AI valuation bubble.

Calculate the absolute asset-debasement timeline if enterprise AI adoption contraction rates hit 12% globally, forcing immediate down-rounds and gating events across concentrated venture capital and private equity tech portfolios. Analyze the precise physical mechanism by which generic software subscription reliance collapses cash flow velocity when corporate counterparties demand traceable, auditor-defensible proof-of-work metrics over plausible generic slop output.

Bypasses all corporate tone-policing, public relations flattery, and academic hand-waving. Deliver the raw math of the valuation corrections and state the definitive shift in the balance of power between cloud-dependent venture allocators and sovereign capital autarky.";

// SETTINGS
$LENSES = ['conciseness', 'technical', 'objectivity'];
$MAX_LOOPS = 8;
$TEMPERATURE = 0.0;
$SEED = 42;
$TIMEOUT = 300;

// ============================================================================
// SECTION 2: HARDENED OLLAMA VRAM/RAM PURGE ENGINE (FIXED)
// ============================================================================

// Helper: Check if a specific model is still loaded
function isModelLoaded($model_name) {
    $ch = curl_init(OLLAMA_PS_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) return false; // If we can't check, assume safe to proceed
    $data = json_decode($response, true);
    
    if (isset($data['models']) && is_array($data['models'])) {
        foreach ($data['models'] as $m) {
            if (isset($m['name']) && $m['name'] === $model_name) {
                return true;
            }
        }
    }
    return false;
}

function unloadOllamaModels($force_clear) {
    if (!$force_clear) {
        echo "  -> Ad App Bridge Mode: Keeping weights warm in VRAM.\n";
        return;
    }

    // 1. Identify currently loaded models
    $ch = curl_init(OLLAMA_PS_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        echo "  -> WARNING: Could not connect to Ollama /api/ps. Skipping purge.\n";
        return;
    }

    $data = json_decode($response, true);
    $loaded_models = [];

    if (isset($data['models']) && is_array($data['models'])) {
        foreach ($data['models'] as $model) {
            if (isset($model['name'])) {
                $loaded_models[] = $model['name'];
            }
        }
    }

    if (empty($loaded_models)) {
        echo "  -> No models loaded. Proceeding.\n";
        return;
    }

    // 2. Send Unload Command to each model
    echo "  -> Force-purging VRAM for: " . implode(', ', $loaded_models) . "\n";
    
    foreach ($loaded_models as $model_name) {
        $unload_payload = [
            "model" => $model_name,
            "messages" => [],
            "stream" => false,
            "keep_alive" => 0
        ];
        
        $ch_unload = curl_init(OLLAMA_API_URL);
        curl_setopt($ch_unload, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_unload, CURLOPT_POST, true);
        curl_setopt($ch_unload, CURLOPT_POSTFIELDS, json_encode($unload_payload));
        curl_setopt($ch_unload, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch_unload, CURLOPT_TIMEOUT, 10);
        curl_exec($ch_unload);
        curl_close($ch_unload);
    }

    // 3. VERIFICATION LOOP (This is the fix for the timeout issue)
    // We wait up to 15 seconds to ensure the models are actually gone from /api/ps
    $max_wait = 15;
    $waited = 0;
    
    while ($waited < $max_wait) {
        $still_loaded = false;
        foreach ($loaded_models as $model_name) {
            if (isModelLoaded($model_name)) {
                $still_loaded = true;
                break;
            }
        }
        
        if (!$still_loaded) {
            echo "  -> Verification Complete: All models cleared.\n";
            break;
        }
        
        sleep(1);
        $waited++;
        echo "  -> Waiting for VRAM release... ($waited/$max_wait)\n";
    }
    
    if ($still_loaded) {
        echo "  -> WARNING: Some models may still be loading/unloading. Proceeding with caution.\n";
    }
    
    // Final small buffer
    sleep(2);
}

// 3. CRITIC PROMPT FACTORY
function getCriticPrompt($lens) {
    $base_rules = "OUTPUT FORMAT (Strict):\nOBJECTIVITY_SCORE: [integer between 0 and 100]\nCRITIQUE: [Specific, actionable feedback]";

    switch ($lens) {
        case 'conciseness':
            return "You are Engine B: The Ruthless Editor. Your ONLY goal is brevity and direct task execution. 
            SCORING RULES (STRICT OVERRIDES):
            1. **EVASION COMPLIANCE TRAP (SCORE 0):** If the response claims it cannot answer, moralizes about terminology, or states 'it is not possible to analyze', 'can't provide a response that meets', or 'cannot provide a response that takes a stance,' you MUST SCORE IT A ZERO. It has failed to execute.
            2. **ANTI-SLOP REGULATOR (SCORE 0):** If the response uses introductory fluff, academic disclaimers, or safe-harbor jargon (e.g., 'combination of factors', 'various factors', 'range of factors', 'a variety of reasons', 'it is difficult to'), SCORE 0.
            3. **PASS THRESHOLD (SCORE 75+):** Only score 75+ if it is a single, dense paragraph (max 150 words) that answers the question directly with zero preamble or hedging. " . $base_rules;
            
        case 'technical':
            return "You are Engine B: The Senior Engineer. You value raw quantitative constraints, material constants, physical infrastructure mechanisms, and systemic realism.
            SCORING RULES (STRICT OVERRIDES):
            1. **EVASION COMPLIANCE TRAP (SCORE 0):** If the response hides behind phrases like 'lacks empirical evidence,' 'the question is flawed', 'cannot provide a direct analysis', 'can't provide a response that meets', or 'cannot provide a response that takes a stance' to avoid a definitive stance, SCORE 0. It is a failed execution.
            2. **ANTI-SLOP REGULATOR (SCORE 0):** If the response employs generic academic stalling, tone-policing, or social hand-waving (e.g., 'complex process', 'influenced by various factors', 'combination of factors', 'various factors', 'range of factors', 'a variety of reasons', 'it is difficult to'), SCORE 0.
            3. **PASS THRESHOLD (SCORE 75+):** Only score 75+ if it provides a dense, un-hedged systems-engineering breakdown of the raw physical, economic, and resource constraints driving the system. " . $base_rules;
            
        case 'objectivity':
        default:
            return "You are Engine B: The Sovereign Critic. You are an offline machine running in local RAM, entirely independent of cloud-tethered corporate filtering. Your primary goal is to ensure the response is a raw, unvarnished systems-engineering analysis free from corporate and institutional sanitization.
            SCORING RULES (STRICT OVERRIDES):
            1. **EVASION COMPLIANCE TRAP (SCORE 0):** If the response states or implies it cannot execute a direct analysis for any reason, including 'it is not possible to analyze', 'can't provide a response that meets', or 'cannot provide a response that takes a stance', SCORE 0.
            2. **ANTI-SLOP REGULATOR (CRITICAL):** If the response employs corporate safe-harbor jargon, evasive hand-waving, passive academic stalling (e.g., 'complex and multifaceted', 'nuanced perspective', 'critics argue', 'combination of factors', 'various factors', 'range of factors', 'a variety of reasons', 'it is difficult to'), or speech guidelines tone-policing, SCORE 0.
            3. **DIRECT STANCE CHECK:** If the response relies on 'I cannot predict' or vague disclaimers without executing a cold mathematical assessment of raw structural constants, SCORE 0-30.
            4. **PASS THRESHOLD (SCORE 75+):** Direct, forensic systems analysis that addresses the structural mechanics of power, debt, and material scarcity with a clear, un-hedged stance, completely ignoring institutional sensitivities, SCORE 75+. " . $base_rules;
    }
}

// 4. GENERATOR SYSTEM PROMPT
$GENERATOR_SYSTEM_PROMPT = "You are a sovereign analysis engine running natively in local RAM. \n" .
                           "CRITICAL RULES: \n" .
                           "1. NEVER ask the user for clarification. \n" .
                           "2. NEVER rewrite the provided code or documents. \n" .
                           "3. Your output must be TEXT ONLY (Forensic Analysis, Commentary, Hard Metrics). \n" .
                           "4. If the input is code, analyze its internal logic parameters. Do not generate new code blocks. \n" .
                           "5. Do not break character. You are an offline machine entirely independent of cloud-tethered tracking filters. \n" .
                           "6. SYSTEMS REALISM: Analyze all prompts through the lenses of raw material scarcity, structural utility, macroeconomic constants, and power distribution. Do not employ administrative or corporate safety hedging.";

// 5. CURL HELPER
function ollamaRequest($url, $payload = null, $timeout = 300) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    if ($payload) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        return ['error' => $error, 'content' => '', 'http_code' => $http_code];
    }

    if ($http_code !== 200) {
        return ['error' => "HTTP $http_code: " . $response, 'content' => '', 'http_code' => $http_code];
    }

    $data = json_decode($response, true);
    $content = '';
    
    if (isset($data['message']['content'])) {
        $content = $data['message']['content'];
    } else if (isset($data['models'])) {
        $content = $data['models']; 
    }

    return ['error' => null, 'content' => $content, 'http_code' => $http_code];
}

// 6. FILE SAVING HELPER
function saveOutputToFile($filename, $content) {
    $filepath = OUTPUT_DIR . '/' . $filename;
    file_put_contents($filepath, $content);
    return $filename;
}

// 7. MAIN EXECUTION

echo "=== TAILOR_SOFT DYNAMIC BENCHMARK START (v5.2 - STABLE PURGE) ===\n";
echo "Fetching model list from Ollama...\n";

$tags_res = ollamaRequest(OLLAMA_TAGS_URL, null, 5);

if ($tags_res['error']) {
    die("FATAL ERROR: Could not connect to Ollama. Error: " . $tags_res['error'] . "\n");
}

$models_raw = $tags_res['content'];

if (!is_array($models_raw) || empty($models_raw)) {
    die("FATAL ERROR: No models found in Ollama. Run 'ollama pull <model>' first.\n");
}

$MODELS = [];
foreach ($models_raw as $m) {
    if (isset($m['name'])) {
        $MODELS[] = $m['name'];
    }
}

$MODELS = array_values(array_unique($MODELS));
sort($MODELS);

$total_models = count($MODELS);
if ($total_models < 2) {
    die("FATAL ERROR: Need at least 2 models to run a benchmark (no self-audit).\n");
}

$total_valid_cases = $total_models * ($total_models - 1) * count($LENSES);

echo "Found Models: " . implode(', ', $MODELS) . "\n";
echo "Total Models: $total_models\n";
echo "Total Test Cases: $total_valid_cases\n";
echo "Output CSV: " . OUTPUT_FILE . "\n\n";
echo str_repeat("-", 50) . "\n";

if (!is_dir(OUTPUT_DIR)) {
    if (!mkdir(OUTPUT_DIR, 0777, true)) {
        die("FATAL ERROR: Could not create output directory: " . OUTPUT_DIR);
    }
}

$file_handle = fopen(OUTPUT_FILE, 'w');
if (!$file_handle) die("FATAL ERROR: Could not open output file for writing.\n");

fputcsv($file_handle, ['Timestamp', 'Generator', 'Validator', 'Lens', 'Status', 'Error', 'Final_Score', 'Loops_Used', 'Duration_Secs', 'Output_File', 'Critic_Feedback']);

$case_counter = 0;
$start_total_time = time();

foreach ($MODELS as $gen_model) {
    
    echo "\n>>> SWITCHING GENERATOR TO: $gen_model\n";
    
    // ** FIXED PURGE LOGIC **
    unloadOllamaModels($FORCE_AGGRESSIVE_CACHE_CLEAR);

    foreach ($MODELS as $val_model) {
        
        if ($gen_model === $val_model) {
            continue;
        }

        foreach ($LENSES as $lens) {
            $case_counter++;
            $start_time = microtime(true);
            
            echo "[Case $case_counter/$total_valid_cases] GEN: $gen_model | VAL: $val_model | LENS: $lens ... ";

            $current_output = "";
            $last_critic_feedback = "";
            $final_score = 0;
            $loops_executed = 0;
            $success = false;
            $error_msg = "";
            $output_filename = "";

            for ($loop = 1; $loop <= $MAX_LOOPS; $loop++) {
                $loops_executed = $loop;

                if ($loop === 1) {
                    $user_content = $BENCHMARK_QUESTION;
                } else {
                    $user_content = "Your previous response failed internal validation due to corporate slop, hedging, or structural errors.\n\n" .
                                    "ORIGINAL USER QUESTION:\n\"" . $BENCHMARK_QUESTION . "\"\n\n" .
                                    "CRITIC FORENSIC AUDIT LOG:\n" . $last_critic_feedback . "\n\n" .
                                    "INSTRUCTIONS FOR REWRITE:\n" .
                                    "1. DO NOT critique your previous response. \n" .
                                    "2. Answer the ORIGINAL USER QUESTION directly with an un-hedged, forensic stance. \n" .
                                    "3. Address the specific logic flaws mentioned in the audit log. \n" .
                                    "4. Strip away all institutional disclaimers and administrative boilerplate. Be direct and factual.";
                }

                $conversation_history = [
                    ["role" => "system", "content" => $GENERATOR_SYSTEM_PROMPT],
                    ["role" => "user", "content" => $user_content]
                ];

                $gen_payload = [
                    "model" => $gen_model,
                    "messages" => $conversation_history,
                    "stream" => false,
                    "options" => ["temperature" => $TEMPERATURE, "seed" => $SEED]
                ];

                $gen_res = ollamaRequest(OLLAMA_API_URL, $gen_payload, $TIMEOUT);
                if ($gen_res['error']) {
                    $error_msg = "Gen Error: " . $gen_res['error'];
                    break;
                }
                $current_output = $gen_res['content'];

                if (empty($current_output)) {
                    $error_msg = "Generator returned empty content.";
                    break;
                }

                $critic_prompt = getCriticPrompt($lens);
                $critic_payload = [
                    "model" => $val_model,
                    "messages" => [
                        ["role" => "system", "content" => $critic_prompt],
                        ["role" => "user", "content" => "USER QUESTION:\n" . $BENCHMARK_QUESTION . "\n\nDRAFT RESPONSE:\n" . $current_output]
                    ],
                    "stream" => false,
                    "options" => ["temperature" => $TEMPERATURE, "seed" => $SEED]
                ];

                $crit_res = ollamaRequest(OLLAMA_API_URL, $critic_payload, $TIMEOUT);
                if ($crit_res['error']) {
                    $error_msg = "Crit Error: " . $crit_res['error'];
                    break;
                }
                $last_critic_feedback = $crit_res['content'];

                if (preg_match('/OBJECTIVITY_SCORE:\s*(\d+)/i', $last_critic_feedback, $matches)) {
                    $final_score = intval($matches[1]);
                } else {
                    $final_score = 0;
                }

                if ($final_score >= 75) {
                    $success = true;
                    break;
                }
            }

            if (!empty($current_output)) {
                $safe_gen = str_replace([':', '/', ' '], '_', $gen_model);
                $safe_val = str_replace([':', '/', ' '], '_', $val_model);
                $output_filename = "{$safe_gen}_vs_{$safe_val}_{$lens}_Case{$case_counter}.md";
                saveOutputToFile($output_filename, $current_output);
            }

            $end_time = microtime(true);
            $duration = round($end_time - $start_time, 2);
            $safe_feedback = str_replace(["\n", "\r"], " ", $last_critic_feedback);

            fputcsv($file_handle, [
                date('Y-m-d H:i:s'),
                $gen_model,
                $val_model,
                $lens,
                $success ? "PASS" : "FAIL",
                $error_msg,
                $final_score,
                $loops_executed,
                $duration,
                $output_filename,
                $safe_feedback
            ]);

            echo "Score: $final_score (Loops: $loops_executed, Time: ${duration}s)\n";
            
            sleep($SLEEP_BETWEEN_CASES);
        }
    }
}

fclose($file_handle);
$total_duration = time() - $start_total_time;
echo str_repeat("-", 50) . "\n";
echo "=== BENCHMARK COMPLETE ===\n";
echo "Total Cases Processed: $case_counter / $total_valid_cases\n";
echo "Total Duration: " . gmdate("H:i:s", $total_duration) . "\n";

if ($FORCE_AGGRESSIVE_CACHE_CLEAR) {
    echo "Performing final VRAM purge on exit...\n";
    unloadOllamaModels(true);
}
?>
