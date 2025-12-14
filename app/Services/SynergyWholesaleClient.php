<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

class SynergyWholesaleClient
{
    private ?SoapClient $client = null;

    private string $resellerId;

    private string $apiKey;

    private string $apiUrl;

    public function __construct(string $resellerId, string $apiKey, ?string $apiUrl = null)
    {
        $this->resellerId = $resellerId;
        $this->apiKey = $apiKey;
        // Default API URL - verify with actual Synergy Wholesale API documentation
        // The WSDL may require authentication or IP whitelisting to access
        // Check the API documentation PDF for the correct endpoint
        $this->apiUrl = $apiUrl ?? config('services.synergy.api_url', 'https://api.synergywholesale.com/soap');
    }

    /**
     * Initialize SOAP client
     */
    private function initialize(): void
    {
        if ($this->client !== null) {
            return;
        }

        try {
            // Try with WSDL first
            $options = [
                'soap_version' => SOAP_1_1,
                'trace' => true,
                'exceptions' => true,
                'stream_context' => stream_context_create([
                    'http' => [
                        'timeout' => 10,
                        'user_agent' => 'DomainMonitor/1.0',
                    ],
                ]),
            ];

            // If URL doesn't end with ?wsdl, try adding it
            $wsdlUrl = $this->apiUrl;
            if (! str_contains($wsdlUrl, '?wsdl') && ! str_contains($wsdlUrl, '&wsdl')) {
                $wsdlUrl = rtrim($wsdlUrl, '/').'?wsdl';
            }

            $this->client = new SoapClient($wsdlUrl, $options);
        } catch (SoapFault $e) {
            Log::error('Synergy Wholesale SOAP client initialization failed', [
                'error' => $e->getMessage(),
                'api_url' => $this->apiUrl,
                'fault_code' => $e->faultcode ?? null,
            ]);

            // Provide helpful error message
            $message = 'Failed to initialize Synergy Wholesale client: '.$e->getMessage();
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'Not Found')) {
                $message .= "\n\nPossible issues:\n";
                $message .= "- WSDL endpoint may require authentication\n";
                $message .= "- IP address may need to be whitelisted\n";
                $message .= "- Verify the correct API endpoint in Synergy Wholesale API documentation\n";
            }

            throw new \RuntimeException($message);
        }
    }

    /**
     * Get domain information including expiry date
     *
     * @return array{domain: string, expiry_date: string|null, registrar: string|null, status: string|null, nameservers: array<int, string>}|null
     */
    public function getDomainInfo(string $domain): ?array
    {
        $this->initialize();

        try {
            // Method name may vary - check Synergy Wholesale API docs for exact method name
            // Common method names: GetDomainInfo, QueryDomain, DomainInfo
            $result = $this->client->__soapCall('GetDomainInfo', [
                [
                    'ResellerID' => $this->resellerId,
                    'APIKey' => $this->apiKey,
                    'Domain' => $domain,
                ],
            ]);

            // Parse SOAP response - structure depends on API
            if (isset($result->Domain)) {
                return [
                    'domain' => $result->Domain,
                    'expiry_date' => $result->ExpiryDate ?? null,
                    'registrar' => $result->Registrar ?? null,
                    'status' => $result->Status ?? null,
                    'nameservers' => $result->Nameservers ?? [],
                ];
            }

            return null;
        } catch (SoapFault $e) {
            Log::warning('Synergy Wholesale API call failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'fault_code' => $e->faultcode ?? null,
            ]);

            return null;
        }
    }

    /**
     * Sync domain expiry date
     */
    public function syncDomainExpiry(string $domain): ?\DateTimeImmutable
    {
        $info = $this->getDomainInfo($domain);

        if (! $info || ! $info['expiry_date']) {
            return null;
        }

        try {
            return new \DateTimeImmutable($info['expiry_date']);
        } catch (\Exception $e) {
            Log::warning('Failed to parse expiry date', [
                'domain' => $domain,
                'expiry_date' => $info['expiry_date'],
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create client from encrypted credentials
     */
    public static function fromEncryptedCredentials(string $resellerId, string $encryptedApiKey, ?string $apiUrl = null): self
    {
        $apiKey = Crypt::decryptString($encryptedApiKey);

        return new self($resellerId, $apiKey, $apiUrl);
    }
}
