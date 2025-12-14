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
        // Default API URL - base URL for NuSOAP server
        // WSDL is accessible at: https://api.synergywholesale.com?wsdl
        $this->apiUrl = $apiUrl ?? config('services.synergy.api_url', 'https://api.synergywholesale.com');
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

            // WSDL URL - append ?wsdl if not present
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
     * Get domain information including expiry date and additional fields
     *
     * @return array{domain: string, expiry_date: string|null, created_date: string|null, domain_status: string|null, auto_renew: string|null, nameservers: array<int, string>, dns_config_name: string|null, registrant_name: string|null, registrant_id_type: string|null, registrant_id: string|null, eligibility_type: string|null, eligibility_valid: bool|null, eligibility_last_check: string|null, registrar: string|null, status: string|null}|null
     */
    public function getDomainInfo(string $domain): ?array
    {
        $this->initialize();

        try {
            // Use domainInfo method with correct parameter structure
            // Parameters: resellerID, apiKey, domainName, associationID
            $request = [
                'resellerID' => $this->resellerId,
                'apiKey' => $this->apiKey,
                'domainName' => $domain,
                'associationID' => '', // Optional, can be empty
            ];

            $result = $this->client->domainInfo($request);

            // Check for errors in response
            if (isset($result->status) && $result->status !== 'OK' && str_starts_with($result->status, 'ERR_')) {
                Log::warning('Synergy Wholesale API returned error', [
                    'domain' => $domain,
                    'status' => $result->status,
                    'error_message' => $result->errorMessage ?? null,
                ]);

                return null;
            }

            // Parse SOAP response - response structure is flat, not wrapped in domainInfoResult
            if (isset($result->domainName) || isset($result->status)) {
                // Extract nameservers - can be array or object
                $nameservers = [];
                if (isset($result->nameServers) && is_array($result->nameServers)) {
                    $nameservers = $result->nameServers;
                } elseif (isset($result->nameservers) && is_array($result->nameservers)) {
                    $nameservers = $result->nameservers;
                }

                // Parse auto_renew (can be 'on', 'off', or boolean)
                $autoRenew = null;
                if (isset($result->autoRenew)) {
                    $autoRenew = is_bool($result->autoRenew) ? $result->autoRenew : (strtolower($result->autoRenew) === 'on');
                }

                // Parse eligibility_valid (can be boolean or integer 0/1)
                $eligibilityValid = null;
                if (isset($result->au_valid_eligibility)) {
                    $eligibilityValid = (bool) $result->au_valid_eligibility;
                } elseif (isset($result->auValidEligibility)) {
                    $eligibilityValid = (bool) $result->auValidEligibility;
                }

                // Parse eligibility_last_check (prefer auEligibilityLastCheck, fallback to au_eligibility_last_check)
                $eligibilityLastCheck = $result->auEligibilityLastCheck ?? $result->au_eligibility_last_check ?? null;

                return [
                    'domain' => $result->domainName ?? $domain,
                    'expiry_date' => $result->domain_expiry ?? $result->expiryDate ?? null,
                    'created_date' => $result->createdDate ?? null,
                    'domain_status' => $result->domain_status ?? null,
                    'auto_renew' => $autoRenew,
                    'nameservers' => $nameservers,
                    'dns_config_name' => $result->dnsConfigName ?? null,
                    'registrant_name' => $result->auRegistrantName ?? null,
                    'registrant_id_type' => $result->auRegistrantIDType ?? null,
                    'registrant_id' => $result->auRegistrantID ?? null,
                    'eligibility_type' => $result->auEligibilityType ?? null,
                    'eligibility_valid' => $eligibilityValid,
                    'eligibility_last_check' => $eligibilityLastCheck,
                    'registrar' => $result->registrar ?? null,
                    'status' => $result->status ?? null,
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
     * List all domains from Synergy Wholesale
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function listDomains(): \Illuminate\Support\Collection
    {
        $this->initialize();

        try {
            $request = [
                'resellerID' => $this->resellerId,
                'apiKey' => $this->apiKey,
            ];

            $result = $this->client->listDomains($request);

            // Extract domains from response
            $domains = [];
            if (isset($result->listDomainsResult) && is_array($result->listDomainsResult)) {
                $domains = $result->listDomainsResult;
            } elseif (isset($result->listDomainsResult)) {
                // If it's a single object, wrap it in an array
                $domains = [$result->listDomainsResult];
            }

            // Filter out domains with errors and return as collection
            return collect($domains)->filter(function ($domain) {
                return isset($domain->status) && $domain->status === 'OK' && isset($domain->domainName);
            });
        } catch (SoapFault $e) {
            Log::error('Synergy Wholesale listDomains failed', [
                'error' => $e->getMessage(),
                'fault_code' => $e->faultcode ?? null,
            ]);

            return collect([]);
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
