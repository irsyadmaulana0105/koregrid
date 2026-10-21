export const statusLabels = {
    draft: 'Draf',
    pending_approval: 'Menunggu persetujuan',
    approved: 'Disetujui',
    packing: 'Dikemas',
    shipped: 'Dikirim',
    completed: 'Selesai',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
};

export const statusStyles = {
    draft: 'bg-slate-100 text-slate-700',
    pending_approval: 'bg-amber-100 text-amber-800',
    approved: 'bg-sky-100 text-sky-800',
    packing: 'bg-indigo-100 text-indigo-800',
    shipped: 'bg-violet-100 text-violet-800',
    completed: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-red-100 text-red-800',
    cancelled: 'bg-slate-200 text-slate-600',
};

const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 });

export const formatRupiah = (value) => (value === null || value === undefined ? '-' : rupiah.format(Number(value)));

export const formatDate = (iso) =>
    iso ? new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
