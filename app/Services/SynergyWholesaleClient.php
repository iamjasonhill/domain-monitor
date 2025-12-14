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
     * @return array{domain: string, expiry_date: string|null, created_date: string|null, domain_status: string|null, auto_renew: string|null, nameservers: array<int, string>|null, nameserver_details: array<int, array{hostname: string|null, ip: string|null, subdomain: string|null}>|null, dns_config_name: string|null, registrant_name: string|null, registrant_id_type: string|null, registrant_id: string|null, eligibility_type: string|null, eligibility_valid: bool|null, eligibility_last_check: string|null, registrar: string|null, status: string|null}|null
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

            // Debug: Log raw response structure to understand available fields
            Log::debug('Synergy Wholesale API raw response', [
                'domain' => $domain,
                'response_keys' => is_object($result) ? array_keys(get_object_vars($result)) : 'not_object',
                'nameServers_type' => isset($result->nameServers) ? gettype($result->nameServers) : 'not_set',
                'nameServers_value' => isset($result->nameServers) ? (is_array($result->nameServers) ? json_encode($result->nameServers) : (string) $result->nameServers) : 'not_set',
            ]);

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
                // Extract nameservers - can be array, object, or structured data
                $nameservers = [];
                $nameserverDetails = [];

                // Check for nameServers (array of strings)
                if (isset($result->nameServers)) {
                    if (is_array($result->nameServers)) {
                        // Could be array of strings or array of objects
                        foreach ($result->nameServers as $ns) {
                            if (is_string($ns)) {
                                $nameservers[] = $ns;
                            } elseif (is_object($ns)) {
                                // Structured nameserver object
                                $nsData = [
                                    'hostname' => $ns->hostname ?? $ns->name ?? $ns->nameserver ?? null,
                                    'ip' => $ns->ip ?? $ns->ipAddress ?? null,
                                    'subdomain' => $ns->subdomain ?? null,
                                ];
                                if ($nsData['hostname']) {
                                    $nameservers[] = $nsData['hostname'];
                                }
                                $nameserverDetails[] = $nsData;
                            }
                        }
                    } elseif (is_object($result->nameServers)) {
                        // Single nameserver object
                        $nsData = [
                            'hostname' => $result->nameServers->hostname ?? $result->nameServers->name ?? $result->nameServers->nameserver ?? null,
                            'ip' => $result->nameServers->ip ?? $result->nameServers->ipAddress ?? null,
                            'subdomain' => $result->nameServers->subdomain ?? null,
                        ];
                        if ($nsData['hostname']) {
                            $nameservers[] = $nsData['hostname'];
                        }
                        $nameserverDetails[] = $nsData;
                    }
                } elseif (isset($result->nameservers) && is_array($result->nameservers)) {
                    // Alternative field name
                    $nameservers = $result->nameservers;
                }

                // Also check for individual nameserver fields (ns1, ns2, ns3, etc.)
                for ($i = 1; $i <= 10; $i++) {
                    $nsField = "ns{$i}";
                    $nsHostnameField = "ns{$i}Hostname";
                    $nsIpField = "ns{$i}Ip";
                    $nsSubdomainField = "ns{$i}Subdomain";

                    if (isset($result->$nsField) || isset($result->$nsHostnameField)) {
                        $nsHostname = $result->$nsHostnameField ?? $result->$nsField ?? null;
                        $nsIp = $result->$nsIpField ?? null;
                        $nsSubdomain = $result->$nsSubdomainField ?? null;

                        if ($nsHostname) {
                            if (! in_array($nsHostname, $nameservers)) {
                                $nameservers[] = $nsHostname;
                            }
                            $nameserverDetails[] = [
                                'hostname' => $nsHostname,
                                'ip' => $nsIp,
                                'subdomain' => $nsSubdomain,
                            ];
                        }
                    }
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
                    'nameservers' => $nameservers, // Array of hostname strings
                    'nameserver_details' => ! empty($nameserverDetails) ? $nameserverDetails : null, // Detailed nameserver info with IP, subdomain, etc.
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
     * Get DNS records for a domain
     *
     * @return array<int, array{host: string, type: string, value: string, ttl: int|null}>|null
     */
    public function getDnsRecords(string $domain): ?array
    {
        $this->initialize();

        try {
            // Try listDNSZone first (might return all records)
            $request = [
                'resellerID' => $this->resellerId,
                'apiKey' => $this->apiKey,
                'domainName' => $domain,
            ];

            $result = $this->client->listDNSZone($request);

            // Check for errors
            if (isset($result->status) && $result->status !== 'OK' && str_starts_with($result->status, 'ERR_')) {
                Log::warning('Synergy Wholesale listDNSZone returned error', [
                    'domain' => $domain,
                    'status' => $result->status,
                    'error_message' => $result->errorMessage ?? null,
                ]);

                return null;
            }

            // Parse DNS records from response
            // Response structure: listDNSZoneResponse contains 'records' which is listDNSZoneArray (array of singleDNSZoneEntry)
            $records = [];

            // Check for records array (could be 'records' or 'dnsRecords')
            $recordsArray = $result->records ?? $result->dnsRecords ?? null;

            if ($recordsArray && is_array($recordsArray)) {
                foreach ($recordsArray as $record) {
                    if (is_object($record)) {
                        // singleDNSZoneEntry structure: hostName, type, content, ttl, prio, id
                        $records[] = [
                            'host' => $record->hostName ?? $record->recordName ?? $record->host ?? $record->hostname ?? '',
                            'type' => $record->type ?? $record->recordType ?? '',
                            'value' => $record->content ?? $record->recordContent ?? $record->value ?? $record->target ?? $record->data ?? '',
                            'ttl' => isset($record->ttl) ? (int) $record->ttl : (isset($record->recordTTL) ? (int) $record->recordTTL : null),
                            'priority' => isset($record->prio) ? (int) $record->prio : (isset($record->recordPrio) ? (int) $record->recordPrio : (isset($record->priority) ? (int) $record->priority : null)),
                            'id' => $record->id ?? $record->recordID ?? null,
                        ];
                    } elseif (is_array($record)) {
                        $records[] = [
                            'host' => $record['hostName'] ?? $record['recordName'] ?? $record['host'] ?? $record['hostname'] ?? '',
                            'type' => $record['type'] ?? $record['recordType'] ?? '',
                            'value' => $record['content'] ?? $record['recordContent'] ?? $record['value'] ?? $record['target'] ?? $record['data'] ?? '',
                            'ttl' => isset($record['ttl']) ? (int) $record['ttl'] : (isset($record['recordTTL']) ? (int) $record['recordTTL'] : null),
                            'priority' => isset($record['prio']) ? (int) $record['prio'] : (isset($record['recordPrio']) ? (int) $record['recordPrio'] : (isset($record['priority']) ? (int) $record['priority'] : null)),
                            'id' => $record['id'] ?? $record['recordID'] ?? null,
                        ];
                    }
                }
            }

            // Log raw response for debugging
            Log::debug('Synergy Wholesale DNS records response', [
                'domain' => $domain,
                'response_keys' => is_object($result) ? array_keys(get_object_vars($result)) : 'not_object',
                'records_count' => count($records),
            ]);

            return ! empty($records) ? $records : null;
        } catch (SoapFault $e) {
            Log::warning('Synergy Wholesale getDnsRecords failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'fault_code' => $e->faultcode ?? null,
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
