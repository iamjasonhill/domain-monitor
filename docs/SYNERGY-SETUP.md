# Synergy Wholesale API Setup

## Credentials Configured

✅ **Reseller ID:** 27549  
✅ **API Key:** Configured (encrypted in database)  
✅ **API URL:** https://api.synergywholesale.com/soap

## Current Status

⚠️ **WSDL Endpoint Issue:** The SOAP WSDL endpoint is returning 404.

### Possible Causes:
1. **IP Whitelisting Required** - Synergy Wholesale may require your server IP to be whitelisted
2. **Authentication Required** - The WSDL may require authentication to access
3. **Incorrect Endpoint** - The API endpoint URL may need to be verified from the official documentation

## Next Steps

1. **Check API Documentation:**
   - Review the Synergy Wholesale API Documentation PDF
   - Verify the correct SOAP endpoint URL
   - Check for any authentication requirements for WSDL access

2. **IP Whitelisting:**
   - Log in to your Synergy Wholesale account
   - Navigate to **Account Functions** > **API Information**
   - Add your server's IP address to the whitelist

3. **Verify Endpoint:**
   - The endpoint may be different (e.g., `/soap.php?wsdl`, `/api/soap?wsdl`)
   - Check if you need to download the WSDL file manually
   - Contact Synergy Wholesale support if needed

4. **Update Credential:**
   ```php
   $credential = App\Models\SynergyCredential::first();
   $credential->api_url = 'correct-endpoint-url-here';
   $credential->save();
   ```

5. **Test Connection:**
   ```bash
   php artisan domains:sync-synergy-expiry --domain=your-domain.com.au
   ```

## API Method Names

The current implementation uses `GetDomainInfo` as the SOAP method name. This may need to be adjusted based on the actual API documentation. Common method names:
- `GetDomainInfo`
- `QueryDomain`
- `DomainInfo`
- `GetDomainDetails`

Check the API documentation PDF for the exact method names and parameters.

## Testing

Once the WSDL endpoint is accessible, test with:
```bash
php artisan domains:sync-synergy-expiry --domain=your-domain.com.au
```

Or sync all .com.au domains:
```bash
php artisan domains:sync-synergy-expiry --all
```

## Resources

- [Synergy Wholesale API Documentation](https://synergywholesale.com/documentation/api-whmcs-modules/)
- [Support Centre](https://synergywholesale.com/support-centre/)

