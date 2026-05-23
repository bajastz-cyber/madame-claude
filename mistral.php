<?php
/**
 * VoAnh - Client API Mistral (cURL only, Hostinger compatible)
 */

require_once dirname(__FILE__) . '/config.php';

class MistralClient {
    private $apiKeys;
    private $currentKeyIndex = 0;

    public function __construct($userApiKey = null) {
        if ($userApiKey && trim($userApiKey) !== '') {
            $this->apiKeys = [trim($userApiKey)];
        } else {
            $this->apiKeys = DEFAULT_MISTRAL_API_KEYS;
        }
    }

    public function chat($messages, $model = 'mistral-large-2512', $options = []) {
        $params = array_merge([
            'temperature' => 0.7,
            'max_tokens'  => 4096,
            'top_p'       => 1,
        ], $options);

        $params['model']    = $model;
        $params['messages'] = $messages;

        $maxTries = count($this->apiKeys) * 2;

        for ($i = 0; $i < $maxTries; $i++) {
            $apiKey = $this->apiKeys[$this->currentKeyIndex];

            try {
                $result = $this->doRequest($apiKey, $params);

                if (isset($result['choices'][0]['message']['content'])) {
                    return [
                        'success' => true,
                        'content' => $result['choices'][0]['message']['content'],
                        'model'   => $model,
                        'usage'   => $result['usage'] ?? [],
                    ];
                }
                throw new Exception('Réponse API invalide');

            } catch (Exception $e) {
                $msg = $e->getMessage();
                voanh_log("API key[$this->currentKeyIndex] error: $msg", 2);

                // Rotation si rate limit ou clé invalide
                if (strpos($msg, '429') !== false || strpos($msg, '401') !== false) {
                    $this->currentKeyIndex = ($this->currentKeyIndex + 1) % count($this->apiKeys);
                }
            }
        }

        return ['success' => false, 'error' => 'Toutes les clés API ont échoué. Vérifiez vos clés Mistral.'];
    }

    private function doRequest($apiKey, $params) {
        $ch = curl_init(MISTRAL_API_ENDPOINT);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'VoAnh/1.0 (PHP cURL)',
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) throw new Exception("cURL: $error");
        if ($httpCode !== 200) {
            $decoded = json_decode($response, true);
            $errMsg  = $decoded['message'] ?? $decoded['error']['message'] ?? "HTTP $httpCode";
            throw new Exception($errMsg);
        }

        $decoded = json_decode($response, true);
        if (!$decoded) throw new Exception('JSON invalide');
        return $decoded;
    }

    public function getModels() {
        return MISTRAL_MODELS;
    }
}

function getMistralClient($userApiKey = null) {
    return new MistralClient($userApiKey);
}
