#!/bin/bash

# Test script for Domain Monitor API
# Usage: ./test-api.sh [api-key]

API_URL="${API_URL:-https://domains.again.com.au}"
API_KEY="${1:-RLptbhFDCu9jTF30xbjV82opeh1sLGheheLsncDWuOaK2N0z6AqHoAWL6WUs260A}"

echo "Testing Domain Monitor API"
echo "URL: $API_URL/api/domains"
echo "=================================="
echo ""

# Test 1: Without authentication (should return 401)
echo "Test 1: Request without authentication (should return 401)"
echo "-----------------------------------------------------------"
curl -s -X GET "$API_URL/api/domains" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  | python3 -m json.tool 2>/dev/null || cat
echo ""
echo ""

# Test 2: With invalid key (should return 401)
echo "Test 2: Request with invalid API key (should return 401)"
echo "-----------------------------------------------------------"
curl -s -X GET "$API_URL/api/domains" \
  -H "Authorization: Bearer invalid-key-12345" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  | python3 -m json.tool 2>/dev/null || cat
echo ""
echo ""

# Test 3: With valid key (should return 200 with domain list)
echo "Test 3: Request with valid API key (should return 200)"
echo "-----------------------------------------------------------"
curl -s -X GET "$API_URL/api/domains" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  | python3 -m json.tool 2>/dev/null || cat
echo ""

