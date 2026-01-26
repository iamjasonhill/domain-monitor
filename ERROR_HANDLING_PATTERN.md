# Error Handling Pattern

## Problem Fixed

The codebase was using **both** `$this->addError()` and `session()->flash('error')` for the same errors, causing:
- Duplicate error messages (appearing twice)
- Inconsistent user experience
- Confusion about which method to use

## Solution: Clear Pattern

### Use `$this->addError('fieldName', 'message')` for:
- **Form field validation errors**
- Errors that relate to a specific input field
- Shows inline next to the field

**Example:**
```php
if (!$credential) {
    $this->addError('dnsRecordHost', 'No active credentials found.');
    return;
}
```

### Use `session()->flash('error', 'message')` for:
- **General errors** not tied to a specific field
- System-level errors (API failures, not found errors, etc.)
- Shows at the top of the page

**Example:**
```php
if (!$record) {
    session()->flash('error', 'DNS record not found.');
    return;
}
```

### Use `session()->flash('message', 'text')` for:
- **Success messages**
- General informational messages
- Shows at the top of the page

**Example:**
```php
session()->flash('message', 'DNS record added successfully!');
```

## Rules

1. ✅ **Never use both** `addError()` and `session()->flash()` for the same error
2. ✅ **Field errors** → Use `addError()`
3. ✅ **General errors** → Use `session()->flash('error')`
4. ✅ **Success messages** → Use `session()->flash('message')`

## Files Fixed

- `app/Livewire/DomainDetail.php` - Removed 5 redundant `session()->flash()` calls

## Pattern Examples

### ✅ Good: Field-specific error
```php
if (empty($this->subdomainName)) {
    $this->addError('subdomainName', 'Subdomain name is required.');
    return;
}
```

### ✅ Good: General error
```php
if (!$record) {
    session()->flash('error', 'DNS record not found.');
    return;
}
```

### ✅ Good: Success message
```php
session()->flash('message', 'DNS record added successfully!');
```

### ❌ Bad: Using both (redundant)
```php
// DON'T DO THIS
$this->addError('dnsRecordHost', 'Error message.');
session()->flash('error', 'Error message.'); // Duplicate!
```
