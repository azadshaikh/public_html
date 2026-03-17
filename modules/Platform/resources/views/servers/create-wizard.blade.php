{{-- Create Server Wizard --}}
<x-app-layout title="Add Server">

    <x-page-header title="Add Server"
        description="Add a new server to your infrastructure" layout="form"
        :actions="[
            [
                'label' => 'Back',
                'href' => route('platform.servers.index'),
                'icon' => 'ri-arrow-left-line',
                'variant' => 'btn-outline-secondary'
            ],
        ]"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Servers', 'href' => route('platform.servers.index')],
            ['label' => 'Create', 'active' => true],
        ]" />

    @php
        $wizardData = json_encode([
            'sshPublicKey' => $sshPublicKey ?? '',
            'sshPrivateKey' => $sshPrivateKey ?? '',
            'sshCommand' => $sshCommand ?? '',
        ]);
    @endphp

    {{-- Pass data via script tag to avoid inline JS parsing issues --}}
    <script type="application/json" id="wizard-data">{!! $wizardData !!}</script>

    {{-- Wizard Container --}}
    <div x-data="wizardData" id="server-wizard">
        {{-- Step 1: Mode Selection Cards --}}
        <div x-show="step === 'mode'" class="row g-4 justify-content-center">
            <div class="col-12">
                <h5 class="text-center mb-4">How would you like to add your server?</h5>
            </div>

            {{-- Connect Existing Server --}}
            <div class="col-md-5">
                <div class="card h-100 cursor-pointer hover-shadow"
                     @click="selectMode('manual')"
                     :class="{ 'border-primary border-2': mode === 'manual' }">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="ri-server-line text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Connect Existing Server</h5>
                        <p class="card-text text-muted small">
                            Server already has HestiaCP installed.<br>
                            Just enter your API credentials to connect.
                        </p>
                        <div class="mt-3">
                            <span class="badge bg-success-subtle text-success">Quick Setup</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Provision New Server --}}
            <div class="col-md-5">
                <div class="card h-100 cursor-pointer hover-shadow"
                     @click="selectMode('provision')"
                     :class="{ 'border-primary border-2': mode === 'provision' }">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="ri-install-line text-success" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="card-title">Provision New Server</h5>
                        <p class="card-text text-muted small">
                            Fresh VPS with Ubuntu or Debian.<br>
                            We'll install HestiaCP for you.
                        </p>
                        <div class="mt-3">
                            <span class="badge bg-info-subtle text-info">Auto Install</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 text-center mt-4">
                <button type="button" class="btn btn-primary" @click="proceedFromMode()" :disabled="!mode">
                    Continue <i class="ri-arrow-right-line ms-1"></i>
                </button>
            </div>
        </div>

        {{-- Step 2a: Manual Mode Form --}}
        <div x-show="step === 'manual'" x-transition>
            <div class="mb-4">
                <button type="button" class="btn btn-link text-muted p-0" @click="step = 'mode'">
                    <i class="ri-arrow-left-line me-1"></i> Back to mode selection
                </button>
            </div>

            <form data-dirty-form class="needs-validation" method="POST" action="{{ route('platform.servers.store') }}" novalidate>
                @csrf
                <input type="hidden" name="creation_mode" value="manual">
                @include('platform::servers.form-manual')
            </form>
        </div>

        {{-- Step 2b: Provision Mode Form --}}
        <div x-show="step === 'provision'" x-transition>
            <div class="mb-4">
                <button type="button" class="btn btn-link text-muted p-0" @click="step = 'mode'">
                    <i class="ri-arrow-left-line me-1"></i> Back to mode selection
                </button>
            </div>

            <form data-dirty-form class="needs-validation" method="POST" action="{{ route('platform.servers.store') }}" novalidate>
                @csrf
                <input type="hidden" name="creation_mode" value="provision">
                <input type="hidden" name="ssh_public_key" x-bind:value="sshPublicKey">
                <input type="hidden" name="ssh_private_key" x-bind:value="sshPrivateKey">
                @include('platform::servers.form-provision', [
                    'sshCommand' => $sshCommand ?? '',
                    'sshPublicKey' => $sshPublicKey ?? '',
                ])
            </form>
        </div>
    </div>

    <style>
        .cursor-pointer { cursor: pointer; }
        .hover-shadow:hover { box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1) !important; transition: box-shadow 0.2s; }
    </style>

    <script data-up-execute>
    (function() {
        const dataEl = document.getElementById('wizard-data');
        const data = dataEl ? JSON.parse(dataEl.textContent) : {};

        function createWizardComponent() {
            return {
                step: 'mode',
                mode: '',
                sshPublicKey: data.sshPublicKey || '',
                sshPrivateKey: data.sshPrivateKey || '',
                sshCommand: data.sshCommand || '',

                selectMode(selectedMode) {
                    this.mode = selectedMode;
                },

                proceedFromMode() {
                    if (this.mode === 'manual') {
                        this.step = 'manual';
                    } else if (this.mode === 'provision') {
                        this.step = 'provision';
                    }
                }
            };
        }

        // Register Alpine component (idempotent across Unpoly re-exec)
        window.__asteroAlpineData = window.__asteroAlpineData || {};

        if (typeof Alpine !== 'undefined') {
            if (!window.__asteroAlpineData.wizardData) {
                Alpine.data('wizardData', createWizardComponent);
                window.__asteroAlpineData.wizardData = true;
            }

            // Re-init the wizard element if needed
            const wizard = document.getElementById('server-wizard');
            if (wizard && !wizard._x_dataStack) {
                Alpine.initTree(wizard);
            }
        } else {
            // Alpine not yet loaded - wait for init event
            document.addEventListener('alpine:init', () => {
                window.__asteroAlpineData = window.__asteroAlpineData || {};
                if (!window.__asteroAlpineData.wizardData) {
                    Alpine.data('wizardData', createWizardComponent);
                    window.__asteroAlpineData.wizardData = true;
                }
            });
        }
    })();
    </script>

</x-app-layout>
