export function resolveScaffoldStatusTabCount(value, statistics) {
    if (value === 'all') {
        return statistics.total ?? 0;
    }

    return statistics[value] ?? 0;
}
