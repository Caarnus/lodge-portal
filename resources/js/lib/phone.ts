export const formatPhone = (value: string | null | undefined): string => {
    const phone = value?.trim() ?? '';
    if (!phone) return '';

    const digits = phone.replace(/\D/g, '');
    if (!phone.startsWith('+') && digits.length === 10) {
        return `(${digits.slice(0, 3)})${digits.slice(3, 6)}-${digits.slice(6)}`;
    }
    if (phone.startsWith('+') && digits.length === 11 && digits.startsWith('1')) {
        return `+1 (${digits.slice(1, 4)})${digits.slice(4, 7)}-${digits.slice(7)}`;
    }

    return phone;
};
