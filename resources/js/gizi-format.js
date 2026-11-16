export function formatPersentaseAkg(persentase, status) {
    if (persentase === null || persentase === undefined) {
        return 'Data tidak tersedia';
    }

    return status ? `${persentase}% (${status})` : `${persentase}%`;
}
