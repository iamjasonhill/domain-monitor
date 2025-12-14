# Simple Authentication & Basic Interface - Plan

## Goal
Add simple authentication and a basic web interface to view and manage domains.

## Approach: Livewire + Tailwind

Since this is a personal project, we'll use:
- **Laravel Breeze** for authentication (with Livewire option)
- **Livewire** for interactive components (less JavaScript needed)
- **Tailwind CSS** for styling
- **Basic admin interface** for domain management

## Implementation Plan

### Phase 1: Authentication Setup

#### Laravel Breeze with Livewire (Recommended)
```bash
composer require laravel/breeze --dev
php artisan breeze:install livewire
npm install && npm run build
```

**Pros:**
- Clean, simple authentication
- Pre-built login/register/reset password views
- Livewire components for interactivity
- Tailwind CSS included
- Less JavaScript needed
- Easy to customize

**Recommendation:** Use Laravel Breeze with Livewire for speed, simplicity, and interactivity.

### Phase 2: Create Superadmin User

**Seeder:** `database/seeders/SuperadminSeeder.php`

```php
User::create([
    'name' => 'Jason Hill',
    'email' => 'jason@jasonhill.com.au',
    'password' => Hash::make('secure-password'),
    'email_verified_at' => now(),
]);
```

**Command:**
```bash
php artisan db:seed --class=SuperadminSeeder
```

### Phase 3: Basic Admin Interface (Livewire Components)

#### Livewire Components Needed:

1. **Dashboard Component** (`app/Livewire/Dashboard.php`)
   - Overview stats:
     - Total domains
     - Active domains
     - Domains expiring soon (next 30 days)
     - Recent health check failures
   - Quick actions:
     - Add new domain
     - Run health check
     - Sync Synergy Wholesale
   - Real-time updates possible with Livewire

2. **Domains List Component** (`app/Livewire/DomainsList.php`)
   - Table view with:
     - Domain name
     - Status (active/inactive)
     - Expiry date
     - Last health check status
     - Platform
     - Hosting provider
     - Actions (view, edit, delete)
   - Filters (Livewire wire:model):
     - Active/Inactive
     - Expiring soon
     - By project
   - Search functionality (Livewire search)
   - Pagination (Livewire built-in)

3. **Domain Detail Component** (`app/Livewire/DomainDetail.php`)
   - Domain information:
     - Basic info (domain, registrar, hosting, platform)
     - Expiry date
     - Synergy Wholesale data (if .com.au)
     - Nameservers
     - Registrant info
   - Health check history:
     - Recent HTTP checks
     - Recent SSL checks
     - Recent DNS checks
   - Platform detection info
   - Hosting detection info
   - Actions:
     - Edit domain (modal or separate page)
     - Run health check (Livewire action)
     - Sync from Synergy Wholesale (Livewire action)

4. **Domain Form Component** (`app/Livewire/DomainForm.php`)
   - Form fields:
     - Domain name
     - Project key
     - Registrar
     - Hosting provider
     - Platform
     - Notes
     - Is active
   - Validation (Livewire validation)
   - Save functionality (Livewire action)
   - Can be used as modal or full page

5. **Health Checks Component** (`app/Livewire/HealthChecksList.php`)
   - List of recent health checks
   - Filter by (Livewire wire:model):
     - Domain
     - Check type
     - Status
     - Date range
   - View check details
   - Pagination

### Phase 4: Livewire Components (No Traditional Controllers Needed)

**Livewire Components:**
- `Dashboard` - Dashboard overview
- `DomainsList` - List domains with filters/search
- `DomainDetail` - Show domain details
- `DomainForm` - Create/edit domain form
- `HealthChecksList` - List health checks with filters
- `HealthCheckDetail` - Show health check details

**Optional Controllers (for non-Livewire routes):**
- `SynergyController` - Sync operations (can be Livewire actions instead)

### Phase 5: Layout & Styling

**Options:**

1. **Tailwind CSS** (if using Breeze - already included)
   - Modern, utility-first CSS
   - Responsive by default
   - Easy to customize

2. **Simple Bootstrap** (if not using Breeze)
   - Quick setup
   - Familiar components

3. **Minimal Custom CSS**
   - Lightweight
   - Full control

**Recommendation:** Use Tailwind (comes with Breeze) for modern, clean UI.

### Phase 6: Navigation

**Layout:**
- Header with:
  - Logo/Brand name
  - Navigation links
  - User dropdown (logout)
- Sidebar (optional) or top nav
- Main content area

**Navigation Items:**
- Dashboard
- Domains
- Health Checks
- Settings (future)

## Implementation Steps

1. **Install Laravel Breeze with Livewire**
   ```bash
   composer require laravel/breeze --dev
   php artisan breeze:install livewire
   npm install && npm run build
   ```

2. **Create Superadmin Seeder**
   ```bash
   php artisan make:seeder SuperadminSeeder
   ```

3. **Create Livewire Components**
   ```bash
   php artisan make:livewire Dashboard
   php artisan make:livewire DomainsList
   php artisan make:livewire DomainDetail
   php artisan make:livewire DomainForm
   php artisan make:livewire HealthChecksList
   ```

4. **Create Views (Livewire will auto-generate)**
   - `resources/views/livewire/dashboard.blade.php`
   - `resources/views/livewire/domains-list.blade.php`
   - `resources/views/livewire/domain-detail.blade.php`
   - `resources/views/livewire/domain-form.blade.php`
   - `resources/views/livewire/health-checks-list.blade.php`

5. **Set up Routes**
   - Protected routes (require auth)
   - Livewire component routes

6. **Add Navigation**
   - Update Breeze layout with navigation
   - Add links to all pages

7. **Test Everything**
   - Login/logout
   - View domains
   - Add/edit domains
   - View health checks

## Quick Start Commands

```bash
# Install Breeze with Livewire
composer require laravel/breeze --dev
php artisan breeze:install livewire

# Install dependencies
npm install && npm run build

# Create superadmin
php artisan make:seeder SuperadminSeeder
# (Edit seeder, then run:)
php artisan db:seed --class=SuperadminSeeder

# Create Livewire components
php artisan make:livewire Dashboard
php artisan make:livewire DomainsList
php artisan make:livewire DomainDetail
php artisan make:livewire DomainForm
php artisan make:livewire HealthChecksList

# Start development
php artisan serve
npm run dev
```

## Future Enhancements

- Charts/graphs for uptime statistics
- Real-time updates (Livewire or Alpine.js)
- Export functionality
- Bulk operations
- Advanced filtering
- Dark mode

## Livewire Benefits

**Why Livewire:**
- ✅ Less JavaScript needed
- ✅ Real-time updates without page refresh
- ✅ Form handling is simpler
- ✅ Search/filter without page reloads
- ✅ Built-in pagination
- ✅ Easy to add interactivity later
- ✅ Familiar PHP syntax

**Example Livewire Component:**
```php
// app/Livewire/DomainsList.php
class DomainsList extends Component
{
    public $search = '';
    public $filterActive = null;
    
    public function render()
    {
        $domains = Domain::query()
            ->when($this->search, fn($q) => $q->where('domain', 'like', "%{$this->search}%"))
            ->when($this->filterActive !== null, fn($q) => $q->where('is_active', $this->filterActive))
            ->paginate(20);
            
        return view('livewire.domains-list', compact('domains'));
    }
}
```

