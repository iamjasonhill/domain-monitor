<?php

namespace App\Services;

class DetectedIssueIdentityService
{
    public function makeIssueId(string $domainId, ?string $propertySlug, string $issueClass): string
    {
        $issueIdentity = [
            'source_domain_id' => $domainId,
            'property_slug' => $propertySlug,
            'issue_class' => $issueClass,
        ];

        return sprintf(
            'dm:%s:%s',
            $domainId !== '' ? $domainId : 'unknown',
            substr(sha1(json_encode($issueIdentity, JSON_THROW_ON_ERROR)), 0, 16)
        );
    }
}
