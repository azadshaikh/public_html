import type { WebsiteDnsInstructions } from '../../../../types/platform';

type WebsiteProvisioningDnsInstructionsProps = {
    instructions: WebsiteDnsInstructions;
};

export function WebsiteProvisioningDnsInstructions({
    instructions,
}: WebsiteProvisioningDnsInstructionsProps) {
    if (instructions.mode === 'managed') {
        const nameservers = instructions.nameservers ?? [];

        if (nameservers.length === 0) {
            return null;
        }

        return (
            <div className="mt-2 rounded-lg border bg-muted/20 p-3">
                <p className="text-xs font-medium text-foreground">
                    Update the domain nameservers to:
                </p>
                <div className="mt-2 grid gap-2">
                    {nameservers.map((nameserver) => (
                        <div
                            key={nameserver}
                            className="rounded-md border bg-background px-2.5 py-2 font-mono text-xs text-foreground"
                        >
                            {nameserver}
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    const records = instructions.records ?? [];

    if (records.length === 0) {
        return null;
    }

    return (
        <div className="mt-2 rounded-lg border bg-muted/20 p-3">
            <p className="text-xs font-medium text-foreground">
                Add these DNS records:
            </p>
            <div className="mt-2 overflow-hidden rounded-md border bg-background">
                <table className="w-full text-xs">
                    <thead className="bg-muted/40 text-left text-muted-foreground">
                        <tr>
                            <th className="px-2.5 py-2 font-medium">Host</th>
                            <th className="px-2.5 py-2 font-medium">Type</th>
                            <th className="px-2.5 py-2 font-medium">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        {records.map((record) => (
                            <tr key={`${record.type}:${record.fqdn}:${record.value}`} className="border-t first:border-t-0">
                                <td className="px-2.5 py-2 align-top">
                                    <div className="font-mono text-foreground">{record.host_label}</div>
                                    <div className="mt-0.5 font-mono text-[11px] text-muted-foreground">
                                        {record.fqdn}
                                    </div>
                                </td>
                                <td className="px-2.5 py-2 align-top font-mono text-foreground">
                                    {record.type}
                                </td>
                                <td className="px-2.5 py-2 align-top font-mono text-foreground break-all">
                                    {record.value}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
