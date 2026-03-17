<?php

namespace Modules\Platform\Http\Controllers;

use App\Enums\ActivityAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\View\View;
use Modules\Platform\Definitions\DomainDnsRecordDefinition;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\DomainDnsRecord;
use Modules\Platform\Services\DomainDnsRecordService;

class DomainDnsController extends PlatformScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly DomainDnsRecordService $dnsRecordService
    ) {}

    public static function middleware(): array
    {
        return (new DomainDnsRecordDefinition)->getMiddleware();
    }

    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        $data = $this->service()->getData($request);

        if ($request->ajax() || $request->wantsJson()) {
            return $this->buildDataGridResponse($data);
        }

        $domainId = $request->integer('domain_id');
        /** @var Domain|null $domain */
        $domain = $domainId ? Domain::query()->find($domainId) : null;

        return view($this->scaffold()->getIndexView(), [
            'config' => $this->service()->getDataGridConfig(),
            'statistics' => $data['statistics'],
            'initialData' => $data,
            'domain' => $domain,
        ]);
    }

    public function create(): View
    {
        $domain = $this->resolveDomainForForm();

        return view($this->scaffold()->getCreateView(), [
            'domainDnsRecord' => null,
            'domain' => $domain,
            'domain_id' => $domain->id,
            'record_types' => $this->dnsRecordService->getDnsTypeOptions(),
            'dns_ttls' => $this->dnsRecordService->getTtlOptions(),
        ]);
    }

    public function edit(int|string $id): View
    {
        /** @var DomainDnsRecord $dnsRecord */
        $dnsRecord = DomainDnsRecord::withTrashed()->findOrFail((int) $id);

        /** @var Domain $domain */
        $domain = Domain::query()->findOrFail((int) $dnsRecord->domain_id);

        return view($this->scaffold()->getEditView(), [
            'domainDnsRecord' => $dnsRecord,
            'domain' => $domain,
            'domain_id' => $domain->id,
            'record_types' => $this->dnsRecordService->getDnsTypeOptions(),
            'dns_ttls' => $this->dnsRecordService->getTtlOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validatedData = $this->validateRequest($request);
        $dnsRecord = $this->service()->create($validatedData);

        $this->handleCreationSideEffects($dnsRecord);
        $this->logActivity($dnsRecord, ActivityAction::CREATE, 'DNS Record created successfully');

        $domainId = (int) ($dnsRecord->domain_id ?? ($validatedData['domain_id'] ?? 0));
        $redirect = route('platform.domains.show', ['domain' => $domainId, 'section' => 'dns']);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => [
                    'title' => 'DNS Record Created!',
                    'message' => 'DNS record created successfully.',
                ],
                'data' => ['id' => $dnsRecord->getKey()],
                'redirect' => $redirect,
            ], 201);
        }

        return redirect()
            ->to($redirect)
            ->with('success', [
                'title' => 'DNS Record Created!',
                'message' => 'DNS record created successfully.',
            ]);
    }

    public function update(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        /** @var DomainDnsRecord $dnsRecord */
        $dnsRecord = DomainDnsRecord::withTrashed()->findOrFail((int) $id);
        $previousValues = $this->capturePreviousValues($dnsRecord);
        $validatedData = $this->validateRequest($request);

        $updatedRecord = $this->service()->update($dnsRecord, $validatedData);

        $this->handleUpdateSideEffects($updatedRecord);
        $this->logActivityWithPreviousValues(
            $updatedRecord,
            ActivityAction::UPDATE,
            'DNS Record updated successfully',
            $previousValues
        );

        $domainId = (int) ($updatedRecord->domain_id ?? ($validatedData['domain_id'] ?? 0));
        $redirect = route('platform.domains.show', ['domain' => $domainId, 'section' => 'dns']);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => [
                    'title' => 'DNS Record Updated!',
                    'message' => 'DNS record updated successfully.',
                ],
                'redirect' => $redirect,
            ]);
        }

        return redirect()
            ->to($redirect)
            ->with('success', [
                'title' => 'DNS Record Updated!',
                'message' => 'DNS record updated successfully.',
            ]);
    }

    protected function service(): DomainDnsRecordService
    {
        return $this->dnsRecordService;
    }

    protected function capturePreviousValues(Model $model): array
    {
        if (! $model instanceof DomainDnsRecord) {
            return [];
        }

        return [
            'name' => $model->name,
            'type' => $model->type,
            'value' => $model->value,
            'ttl' => $model->ttl,
            'disabled' => $model->disabled,
        ];
    }

    private function resolveDomainForForm(): Domain
    {
        $domainId = request()->integer('domain_id');
        abort_unless((bool) $domainId, 404, 'domain_id is required');

        /** @var Domain $domain */
        $domain = Domain::query()->findOrFail($domainId);

        return $domain;
    }
}
