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

    /**
     * Check if a domain is an Australian TLD that Synergy Wholesale handles
     *
     * @param  string  $domain  Domain name to check
     * @return bool True if domain is an Australian TLD (.au, .com.au, .net.au, .org.au, etc.)
     */
    public static function isAustralianTld(string $domain): bool
    {
        // Australian TLDs that Synergy Wholesale handles
        // Direct .au domains: .au
        // Two-part TLDs: .com.au, .net.au, .org.au, .edu.au, .gov.au, .asn.au, .id.au
        // Match either two-part TLDs OR direct .au (but not .com.au when checking for .au)
        return (bool) preg_match('/\.(com|net|org|edu|gov|asn|id)\.au$|\.au$/', $domain);
    }

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
     * @return array{domain: string, expiry_date: string|null, created_date: string|null, domain_status: string|null, auto_renew: bool|null, nameservers: array<int, string>|null, nameserver_details: array<int, array{hostname: string|null, ip: string|null, subdomain: string|null}>|null, dns_config_name: string|null, dns_config_id: int|null, registrant_name: string|null, registrant_id_type: string|null, registrant_id: string|null, eligibility_type: string|null, eligibility_valid: bool|null, eligibility_last_check: string|null, au_policy_id: string|null, au_policy_desc: string|null, au_compliance_reason: string|null, au_association_id: string|null, domain_roid: string|null, registry_id: string|null, id_protect: string|null, categories: array<int, mixed>|null, registrar: string|null, status: string|null}|null
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
                $eligibilityLastCheck = $eligibilityLastCheck !== null ? (string) $eligibilityLastCheck : null;

                $expiryDate = $result->domain_expiry ?? $result->expiryDate ?? null;
                $expiryDate = $expiryDate !== null ? (string) $expiryDate : null;

                $createdDate = $result->createdDate ?? null;
                $createdDate = $createdDate !== null ? (string) $createdDate : null;

                $domainStatus = $result->domain_status ?? null;
                $domainStatus = $domainStatus !== null ? (string) $domainStatus : null;

                // Parse additional .au specific fields
                $auPolicyId = isset($result->auPolicyID) ? (string) $result->auPolicyID : null;
                $auPolicyDesc = isset($result->auPolicyIDDesc) ? (string) $result->auPolicyIDDesc : null;
                $auComplianceReason = isset($result->auComplianceReason) ? (string) $result->auComplianceReason : null;
                $auAssociationId = isset($result->auAssociationID) ? (string) $result->auAssociationID : null;

                // Parse registry and domain identifiers
                $domainRoid = isset($result->domainRoid) ? (string) $result->domainRoid : null;
                $registryId = isset($result->registryID) ? (string) $result->registryID : null;

                // Parse DNS config ID (numeric, we already have name)
                $dnsConfigId = isset($result->dnsConfig) ? (is_numeric($result->dnsConfig) ? (int) $result->dnsConfig : null) : null;

                // Parse ID protection status
                $idProtect = isset($result->idProtect) ? (string) $result->idProtect : null;

                // Parse categories (array)
                $categories = null;
                if (isset($result->categories)) {
                    if (is_array($result->categories)) {
                        $categories = $result->categories;
                    } elseif (is_string($result->categories)) {
                        // Try to decode if it's a JSON string
                        $decoded = json_decode($result->categories, true);
                        $categories = is_array($decoded) ? $decoded : [$result->categories];
                    }
                }

                return [
                    'domain' => isset($result->domainName) ? (string) $result->domainName : $domain,
                    'expiry_date' => $expiryDate,
                    'created_date' => $createdDate,
                    'domain_status' => $domainStatus,
                    'auto_renew' => $autoRenew,
                    'nameservers' => $nameservers, // Array of hostname strings
                    'nameserver_details' => ! empty($nameserverDetails) ? $nameserverDetails : null, // Detailed nameserver info with IP, subdomain, etc.
                    'dns_config_name' => isset($result->dnsConfigName) ? (string) $result->dnsConfigName : null,
                    'dns_config_id' => $dnsConfigId,
                    'registrant_name' => isset($result->auRegistrantName) ? (string) $result->auRegistrantName : null,
                    'registrant_id_type' => isset($result->auRegistrantIDType) ? (string) $result->auRegistrantIDType : null,
                    'registrant_id' => isset($result->auRegistrantID) ? (string) $result->auRegistrantID : null,
                    'eligibility_type' => isset($result->auEligibilityType) ? (string) $result->auEligibilityType : null,
                    'eligibility_valid' => $eligibilityValid,
                    'eligibility_last_check' => $eligibilityLastCheck,
                    'au_policy_id' => $auPolicyId,
                    'au_policy_desc' => $auPolicyDesc,
                    'au_compliance_reason' => $auComplianceReason,
                    'au_association_id' => $auAssociationId,
                    'domain_roid' => $domainRoid,
                    'registry_id' => $registryId,
                    'id_protect' => $idProtect,
                    'categories' => $categories,
                    'registrar' => isset($result->registrar) ? (string) $result->registrar : null,
                    'status' => isset($result->status) ? (string) $result->status : null,
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
            // The SOAP response structure is: $result->domainList (array of domain objects)
            $domains = [];

            if (isset($result->domainList) && is_array($result->domainList)) {
                $domains = $result->domainList;
            } elseif (isset($result->domainList->item)) {
                // Fallback: if domainList is an object with item property
                $items = is_array($result->domainList->item)
                    ? $result->domainList->item
                    : [$result->domainList->item];

                $domains = $items;
            } elseif (isset($result->listDomainsResult)) {
                // Fallback to old structure if present
                if (is_array($result->listDomainsResult)) {
                    $domains = $result->listDomainsResult;
                } else {
                    $domains = [$result->listDomainsResult];
                }
            }

            $domains = array_values(array_filter($domains, 'is_object'));
            /** @var array<int, object> $domains */

            // Filter out domains with errors and return as collection
            /** @var \Illuminate\Support\Collection<int, object> $filtered */
            $filtered = collect($domains)->filter(function (object $domain): bool {
                return isset($domain->status) && $domain->status === 'OK' && isset($domain->domainName);
            })->values();

            return $filtered;
        } catch (SoapFault $e) {
            Log::error('Synergy Wholesale listDomains failed', [
                'error' => $e->getMessage(),
                'fault_code' => $e->faultcode ?? null,
            ]);

            /** @var \Illuminate\Support\Collection<int, object> $empty */
            $empty = collect([]);

            return $empty;
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
     * Add a DNS record
     *
     * @param  string  $domain  Domain name
     * @param  string  $recordName  Record name (host/subdomain)
     * @param  string  $recordType  Record type (A, AAAA, CNAME, MX, NS, TXT, SRV)
     * @param  string  $recordContent  Record value/content
     * @param  int  $recordTTL  TTL in seconds
     * @param  int  $recordPrio  Priority (for MX records, typically 10-100)
     * @return array{status: string, error_message: string|null, record_id: string|null}|null
     */
    public function addDnsRecord(string $domain, string $recordName, string $recordType, string $recordContent, int $recordTTL = 300, int $recordPrio = 0): ?array
    {
        $this->initialize();

        try {
            $request = [
                'resellerID' => $this->resellerId,
                'apiKey' => $this->apiKey,
                'domainName' => $domain,
                'recordName' => $recordName,
                'recordType' => strtoupper($recordType),
                'recordContent' => $recordContent,
                'recordTTL' => $recordTTL,
                'recordPrio' => $recordPrio,
            ];

            Log::info('Synergy Wholesale addDNSRecord request', $request);

            $result = $this->client->addDNSRecord($request);

            Log::info('Synergy Wholesale addDNSRecord response', [
                'domain' => $domain,
                'status' => $result->status ?? 'unknown',
                'id' => $result->id ?? null,
                'errorMessage' => $result->errorMessage ?? null,
                'result_raw' => (array) $result,
            ]);

            // Check for errors
            if (isset($result->status) && $result->status !== 'OK' && str_starts_with($result->status, 'ERR_')) {
                Log::warning('Synergy Wholesale addDNSRecord returned error', [
                    'domain' => $domain,
                    'record_name' => $recordName,
                    'status' => $result->status,
                    'error_message' => $result->errorMessage ?? null,
                ]);

                return [
                    'status' => $result->status,
                    'error_message' => $result->errorMessage ?? null,
                    'record_id' => null,
                ];
            }

            return [
                'status' => $result->status ?? 'OK',
                'error_message' => $result->errorMessage ?? null,
                'record_id' => $result->id ?? null,
            ];
        } catch (SoapFault $e) {
            Log::error('Synergy Wholesale addDNSRecord failed', [
                'domain' => $domain,
                'record_name' => $recordName,
                'error' => $e->getMessage(),
                'fault_code' => $e->faultcode ?? null,
            ]);

            return [
                'status' => 'ERROR',
                'error_message' => $e->getMessage(),
                'record_id' => null,
            ];
        }
    }

    /**
     * Update a DNS record
     *
     * @param  string  $domain  Domain name
     * @param  string  $recordId  Record ID from Synergy Wholesale
     * @param  string  $recordName  Record name (host/subdomain)
     * @param  string  $recordType  Record type (A, AAAA, CNAME, MX, NS, TXT, SRV)
     * @param  string  $recordContent  Record value/content
     * @param  int  $recordTTL  TTL in seconds
     * @param  int  $recordPrio  Priority (for MX records, typically 10-100)
     * @return array{status: string, error_message: string|null}|null
     */
    public function updateDnsRecord(string $domain, string $recordId, string $recordName, string $recordType, string $recordContent, int $recordTTL = 300, int $recordPrio = 0): ?array
    {
        $this->initialize();

        try {
            $request = [
                'resellerID' => $this->resellerId,
                'apiKey' => $this->apiKey,
                'domainName' => $domain,
                'recordName' => $recordName,
                'recordType' => strtoupper($recordType),
                'recordContent' => $recordContent,
                'recordTTL' => (string) $recordTTL, // API expects string for TTL in update
                'recordID' => $recordId,
            ];

            Log::info('Synergy Wholesale updateDNSRecord request', $request);

            $result = $this->client->updateDNSRecord($request);

            Log::info('Synergy Wholesale updateDNSRecord response', [
                'domain' => $domain,
                'record_id' => $recordId,
                'status' => $result->status ?? 'unknown',
                'errorMessage' => $result->errorMessage ?? null,
                'result_raw' => (array) $result,
            ]);

            // Check for errors
            if (isset($result->status) && $result->status !== 'OK' && str_starts_with($result->status, 'ERR_')) {
                Log::warning('Synergy Wholesale updateDNSRecord returned error', [
                    'domain' => $domain,
                    'record_id' => $recordId,
                    'status' => $result->status,
                    'error_message' => $result->errorMessage ?? null,
                ]);

                return [
                    'status' => $result->status,
                    'error_message' => $result->errorMessage ?? null,
                ];
            }

            return [
                'status' => $result->status ?? 'OK',
                'error_message' => $result->errorMessage ?? null,
            ];
        } catch (SoapFault $e) {
            Log::error('Synergy Wholesale updateDNSRecord failed', [
                'domain' => $domain,
                'record_id' => $recordId,
                'error' => $e->getMessage(),
                'fault_code' => $e->faultcode ?? null,
            ]);

            return [
                'status' => 'ERROR',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a DNS record
     *
     * @param  string  $domain  Domain name
     * @param  string  $recordId  Record ID from Synergy Wholesale
     * @return array{status: string, error_message: string|null}|null
     */
    public function deleteDnsRecord(string $domain, string $recordId): ?array
    {
        $this->initialize();

        try {
            $request = [
                'resellerID' => $this->resellerId,
                'apiKey' => $this->apiKey,
                'domainName' => $domain,
                'recordID' => $recordId,
            ];

            $result = $this->client->deleteDNSRecord($request);

            // Check for errors
            if (isset($result->status) && $result->status !== 'OK' && str_starts_with($result->status, 'ERR_')) {
                Log::warning('Synergy Wholesale deleteDNSRecord returned error', [
                    'domain' => $domain,
                    'record_id' => $recordId,
                    'status' => $result->status,
                    'error_message' => $result->errorMessage ?? null,
                ]);

                return [
                    'status' => $result->status,
                    'error_message' => $result->errorMessage ?? null,
                ];
            }

            return [
                'status' => $result->status ?? 'OK',
                'error_message' => $result->errorMessage ?? null,
            ];
        } catch (SoapFault $e) {
            Log::error('Synergy Wholesale deleteDNSRecord failed', [
                'domain' => $domain,
                'record_id' => $recordId,
                'error' => $e->getMessage(),
                'fault_code' => $e->faultcode ?? null,
            ]);

            return [
                'status' => 'ERROR',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get account balance/credit
     *
     * @return array{status: string, balance: float|null, error_message: string|null}|null
     */
    public function getBalance(): ?array
    {
        $this->initialize();

        try {
            $request = [
                'resellerID' => $this->resellerId,
                'apiKey' => $this->apiKey,
            ];

            $result = $this->client->balanceQuery($request);

            // Check for errors
            if (isset($result->status) && $result->status !== 'OK' && str_starts_with($result->status, 'ERR_')) {
                Log::warning('Synergy Wholesale balanceQuery returned error', [
                    'status' => $result->status,
                    'error_message' => $result->errorMessage ?? null,
                ]);

                return [
                    'status' => $result->status,
                    'balance' => null,
                    'error_message' => $result->errorMessage ?? null,
                ];
            }

            // Parse balance - can be string or float
            $balance = null;
            if (isset($result->balance)) {
                $balance = is_numeric($result->balance) ? (float) $result->balance : null;
            }

            return [
                'status' => $result->status ?? 'OK',
                'balance' => $balance,
                'error_message' => $result->errorMessage ?? null,
            ];
        } catch (SoapFault $e) {
            Log::error('Synergy Wholesale balanceQuery failed', [
                'error' => $e->getMessage(),
                'fault_code' => $e->faultcode ?? null,
            ]);

            return [
                'status' => 'ERROR',
                'balance' => null,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Renew a domain
     *
     * @param  string  $domain  Domain name to renew
     * @param  int  $years  Number of years to renew (default 1)
     * @return array{status: string, error_message: string|null, new_expiry_date: string|null}|null
     */
    public function renewDomain(string $domain, int $years = 1): ?array
    {
        $this->initialize();

        try {
            $request = [
                'resellerID' => $this->resellerId,
                'apiKey' => $this->apiKey,
                'domainName' => $domain,
                'years' => $years,
            ];

            $result = $this->client->renewDomain($request);

            // Check for errors
            if (isset($result->status) && $result->status !== 'OK' && str_starts_with($result->status, 'ERR_')) {
                Log::warning('Synergy Wholesale DomainRenew returned error', [
                    'domain' => $domain,
                    'status' => $result->status,
                    'error_message' => $result->errorMessage ?? null,
                ]);

                return [
                    'status' => $result->status,
                    'error_message' => $result->errorMessage ?? null,
                    'new_expiry_date' => null,
                ];
            }

            // Parse new expiry date if provided
            $newExpiryDate = null;
            if (isset($result->domain_expiry) || isset($result->expiryDate)) {
                $newExpiryDate = $result->domain_expiry ?? $result->expiryDate ?? null;
            }

            return [
                'status' => $result->status ?? 'OK',
                'error_message' => $result->errorMessage ?? null,
                'new_expiry_date' => $newExpiryDate,
            ];
        } catch (SoapFault $e) {
            Log::error('Synergy Wholesale DomainRenew failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'fault_code' => $e->faultcode ?? null,
            ]);

            return [
                'status' => 'ERROR',
                'error_message' => $e->getMessage(),
                'new_expiry_date' => null,
            ];
        }
    }

    /**
     * Get domain contact information (registrant, admin, tech, billing)
     *
     * @return array{registrant: array<string, mixed>|null, admin: array<string, mixed>|null, tech: array<string, mixed>|null, billing: array<string, mixed>|null, status: string, error_message: string|null}|null
     */
    public function getDomainContacts(string $domain): ?array
    {
        $this->initialize();

        try {
            $request = [
                'resellerID' => $this->resellerId,
                'apiKey' => $this->apiKey,
                'domainName' => $domain,
            ];

            $result = $this->client->rawDomainContacts($request);

            // Check for errors
            if (isset($result->status) && $result->status !== 'OK' && str_starts_with($result->status, 'ERR_')) {
                Log::warning('Synergy Wholesale rawDomainContacts returned error', [
                    'domain' => $domain,
                    'status' => $result->status,
                    'error_message' => $result->errorMessage ?? null,
                ]);

                return [
                    'registrant' => null,
                    'admin' => null,
                    'tech' => null,
                    'billing' => null,
                    'status' => $result->status,
                    'error_message' => $result->errorMessage ?? null,
                ];
            }

            // Parse contact information
            $contacts = [
                'registrant' => $this->parseContact($result->registrant ?? null),
                'admin' => $this->parseContact($result->admin ?? null),
                'tech' => $this->parseContact($result->tech ?? null),
                'billing' => $this->parseContact($result->billing ?? null),
                'status' => $result->status ?? 'OK',
                'error_message' => null,
            ];

            return $contacts;
        } catch (SoapFault $e) {
            Log::error('Synergy Wholesale rawDomainContacts failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'fault_code' => $e->faultcode ?? null,
            ]);

            return [
                'registrant' => null,
                'admin' => null,
                'tech' => null,
                'billing' => null,
                'status' => 'ERROR',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parse contact object into array
     *
     * @param  object|array<string, mixed>|null  $contact
     * @return array<string, mixed>|null
     */
    private function parseContact($contact): ?array
    {
        if (! $contact) {
            return null;
        }

        if (is_array($contact)) {
            return $contact;
        }

        // $contact is an object at this point
        return [
            'name' => $contact->name ?? $contact->contactName ?? null,
            'email' => $contact->email ?? $contact->contactEmail ?? null,
            'phone' => $contact->phone ?? $contact->contactPhone ?? null,
            'organization' => $contact->organization ?? $contact->org ?? null,
            'address' => $contact->address ?? $contact->street ?? null,
            'city' => $contact->city ?? null,
            'state' => $contact->state ?? $contact->province ?? null,
            'postal_code' => $contact->postalCode ?? $contact->postcode ?? null,
            'country' => $contact->country ?? $contact->countryCode ?? null,
        ];
    }

    /**
     * Check if domain renewal is required
     *
     * @return array{renewal_required: bool, can_renew: bool, days_until_expiry: int|null, error_message: string|null}|null
     */
    public function checkRenewalRequired(string $domain): ?array
    {
        $this->initialize();

        try {
            $request = [
                'resellerID' => $this->resellerId,
                'apiKey' => $this->apiKey,
                'domainName' => $domain,
            ];

            // Try domainRenewRequired first
            $renewRequiredResult = $this->client->domainRenewRequired($request);
            $renewalRequired = isset($renewRequiredResult->renewRequired) && (bool) $renewRequiredResult->renewRequired;

            // Also check canRenewDomain
            $canRenewResult = $this->client->canRenewDomain($request);
            $canRenew = isset($canRenewResult->canRenew) && (bool) $canRenewResult->canRenew;

            // Get expiry info for days calculation
            $domainInfo = $this->getDomainInfo($domain);
            $daysUntilExpiry = null;
            if ($domainInfo && $domainInfo['expiry_date']) {
                try {
                    $expiry = new \DateTimeImmutable($domainInfo['expiry_date']);
                    $now = new \DateTimeImmutable;
                    $daysUntilExpiry = (int) $now->diff($expiry)->days;
                } catch (\Exception $e) {
                    // Invalid date
                }
            }

            return [
                'renewal_required' => $renewalRequired,
                'can_renew' => $canRenew,
                'days_until_expiry' => $daysUntilExpiry,
                'error_message' => null,
            ];
        } catch (SoapFault $e) {
            Log::error('Synergy Wholesale renewal check failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'fault_code' => $e->faultcode ?? null,
            ]);

            return [
                'renewal_required' => false,
                'can_renew' => false,
                'days_until_expiry' => null,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * List non-compliant .au domains
     *
     * @return array<int, array{domain: string, reason: string|null}>|null
     */
    public function listNonCompliantAuDomains(): ?array
    {
        $this->initialize();

        try {
            $request = [
                'resellerID' => $this->resellerId,
                'apiKey' => $this->apiKey,
            ];

            $result = $this->client->listAuNonCompliantDomains($request);

            // Check for errors
            if (isset($result->status) && $result->status !== 'OK' && str_starts_with($result->status, 'ERR_')) {
                Log::warning('Synergy Wholesale listAuNonCompliantDomains returned error', [
                    'status' => $result->status,
                    'error_message' => $result->errorMessage ?? null,
                ]);

                return null;
            }

            // Parse non-compliant domains
            $domains = [];
            $domainList = $result->domainList ?? $result->domains ?? [];

            if (is_array($domainList)) {
                foreach ($domainList as $domain) {
                    if (is_object($domain)) {
                        $domains[] = [
                            'domain' => $domain->domainName ?? $domain->domain ?? '',
                            'reason' => $domain->reason ?? $domain->complianceReason ?? null,
                        ];
                    }
                }
            }

            return $domains;
        } catch (SoapFault $e) {
            Log::error('Synergy Wholesale listAuNonCompliantDomains failed', [
                'error' => $e->getMessage(),
                'fault_code' => $e->faultcode ?? null,
            ]);

            return null;
        }
    }

    /**
     * Check if domain is locked (transfer protection)
     *
     * @return array{locked: bool, error_message: string|null}|null
     */
    public function getDomainLockStatus(string $domain): ?array
    {
        $this->initialize();

        try {
            // Check domain info for lock status
            $domainInfo = $this->getDomainInfo($domain);

            if (! $domainInfo) {
                return [
                    'locked' => false,
                    'error_message' => 'Could not retrieve domain information',
                ];
            }

            // Lock status might be in domain_status or a separate field
            // Common values: 'ok', 'clientTransferProhibited', 'serverTransferProhibited'
            $status = $domainInfo['domain_status'] ?? '';
            $isLocked = str_contains(strtolower($status), 'transferprohibited') || str_contains(strtolower($status), 'lock');

            return [
                'locked' => $isLocked,
                'error_message' => null,
            ];
        } catch (SoapFault $e) {
            Log::error('Synergy Wholesale getDomainLockStatus failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'fault_code' => $e->faultcode ?? null,
            ]);

            return [
                'locked' => false,
                'error_message' => $e->getMessage(),
            ];
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
