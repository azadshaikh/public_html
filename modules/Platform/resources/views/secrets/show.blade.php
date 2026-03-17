<x-app-layout :title="'Secret: ' . $secret->key">

    <x-page-header :title="'Secret: ' . $secret->key"
        description="View secret details" :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Secrets', 'href' => route('platform.secrets.index')],
            ['label' => '#' . $secret->id, 'active' => true],
        ]"
        :actions="[
            [
                'type' => 'link',
                'label' => 'Edit',
                'icon' => 'ri-pencil-line',
                'href' => route('platform.secrets.edit', $secret->id),
            ]
        ]" />

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Secret Information</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <th class="ps-0" scope="row" style="width: 200px;">Key</th>
                                    <td class="text-muted">{{ $secret->key }}</td>
                                </tr>
                                <tr>
                                    <th class="ps-0" scope="row">Type</th>
                                    <td><span class="badge bg-light text-dark text-uppercase">{{ $secret->type }}</span></td>
                                </tr>
                                <tr>
                                    <th class="ps-0" scope="row">Related To</th>
                                    <td>
                                        @if($secret->secretable_type)
                                            <span class="badge bg-info-subtle text-info">
                                                {{ class_basename($secret->secretable_type) }} #{{ $secret->secretable_id }}
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th class="ps-0" scope="row">Status</th>
                                    <td>
                                        @if($secret->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge text-bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th class="ps-0" scope="row">Created At</th>
                                    <td class="text-muted">{{ $secret->created_at->format('M d, Y H:i:s') }}</td>
                                </tr>
                                <tr>
                                    <th class="ps-0" scope="row">Last Updated</th>
                                    <td class="text-muted">{{ $secret->updated_at->format('M d, Y H:i:s') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                     <h5 class="card-title mb-0">Value</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning" role="alert">
                        <i class="ri-alert-line me-1"></i> The secret value is encrypted. You can view the decrypted value below only if you have the necessary permissions.
                    </div>

                    <div x-data="{
                        show: false,
                        decryptedValue: '',
                        loading: false,
                        fetchValue() {
                            this.loading = true;
                            fetch('{{ route('platform.secrets.show', $secret->id) }}', {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            })
                            .then(response => response.json())
                            .then(data => {
                                this.decryptedValue = data.secret.decrypted_value;
                                this.show = true;
                            })
                            .catch(error => {
                                console.error('Error fetching secret:', error);
                                alert('Failed to retrieve secret value.');
                            })
                            .finally(() => {
                                this.loading = false;
                            });
                        }
                    }">
                        <div x-show="!show">
                            <button @click="fetchValue" class="btn btn-outline-primary" :disabled="loading">
                                <span x-show="loading" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                <i x-show="!loading" class="ri-eye-line me-1"></i>
                                Reveal Value
                            </button>
                        </div>

                        <div x-show="show" x-cloak>
                            <div class="input-group">
                                <input type="text" class="form-control" x-model="decryptedValue" readonly>
                                <button class="btn btn-outline-secondary" type="button" @click="navigator.clipboard.writeText(decryptedValue)">
                                    <i class="ri-file-copy-line"></i>
                                </button>
                                <button class="btn btn-outline-secondary" type="button" @click="show = false; decryptedValue = ''">
                                    <i class="ri-eye-off-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

</x-app-layout>
