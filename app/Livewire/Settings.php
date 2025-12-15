<?php

namespace App\Livewire;

use App\Models\SynergyCredential;
use App\Services\SynergyWholesaleClient;
use Livewire\Component;

class Settings extends Component
{
    public ?float $synergyBalance = null;

    public ?string $synergyBalanceError = null;

    public bool $loadingBalance = false;

    public function loadSynergyBalance(): void
    {
        $this->loadingBalance = true;
        $this->synergyBalance = null;
        $this->synergyBalanceError = null;

        try {
            $credential = SynergyCredential::where('is_active', true)->first();

            if (! $credential) {
                $this->synergyBalanceError = 'No active Synergy Wholesale credentials found.';
                $this->loadingBalance = false;

                return;
            }

            $client = SynergyWholesaleClient::fromEncryptedCredentials(
                $credential->reseller_id,
                $credential->encrypted_api_key
            );

            $result = $client->getBalance();

            if ($result && $result['status'] === 'OK' && $result['balance'] !== null) {
                $this->synergyBalance = $result['balance'];
            } else {
                $this->synergyBalanceError = $result['error_message'] ?? 'Failed to retrieve balance.';
            }
        } catch (\Exception $e) {
            $this->synergyBalanceError = 'Error: '.$e->getMessage();
        } finally {
            $this->loadingBalance = false;
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.settings');
    }
}
