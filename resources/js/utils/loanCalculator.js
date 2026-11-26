/**
 * Tarif premi tahunan asuransi terhadap harga kendaraan (OTR).
 * Harus selalu konsisten dengan App\Enums\InsuranceType & LoanCalculatorService di backend.
 *
 * @type {Record<string, { rate: number, percentLabel: string, description: string }>}
 */
export const INSURANCE_OPTIONS = {
    'All Risk': {
        rate: 0.025,
        percentLabel: '2,5% / tahun',
        description: 'Perlindungan menyeluruh terhadap kerusakan ringan hingga kehilangan.',
    },
    Kombinasi: {
        rate: 0.016,
        percentLabel: '1,6% / tahun',
        description: 'All Risk tahun pertama, dilanjutkan TLO untuk tahun berikutnya.',
    },
    TLO: {
        rate: 0.008,
        percentLabel: '0,8% / tahun',
        description: 'Total Loss Only — penggantian jika kehilangan atau kerusakan >= 75%.',
    },
};

export const TENOR_OPTIONS = [12, 24, 36, 48, 60];
export const ANNUAL_FLAT_INTEREST_RATE = 6.0;

/**
 * Menghitung simulasi pinjaman dan angsuran bulanan secara real-time.
 *
 * @param {{ vehiclePrice: number, downPayment: number, insuranceType: string, tenorMonths: number }} input
 * @returns {import('../types/models.js').LoanBreakdown | null}
 */
export function calculateLoan({ vehiclePrice, downPayment, insuranceType, tenorMonths }) {
    const price = Number(vehiclePrice);
    const dp = Number(downPayment);
    const tenor = Number(tenorMonths);
    const insurance = INSURANCE_OPTIONS[insuranceType];

    if (
        !Number.isFinite(price)
        || price <= 0
        || !Number.isFinite(dp)
        || dp < 0
        || dp >= price
        || !insurance
        || !TENOR_OPTIONS.includes(tenor)
    ) {
        return null;
    }

    const years = tenor / 12;
    const principalVehicle = price - dp;
    const insurancePremium = Math.round(price * insurance.rate * years);
    const totalPrincipal = principalVehicle + insurancePremium;
    const totalInterest = Math.round(totalPrincipal * (ANNUAL_FLAT_INTEREST_RATE / 100) * years);
    const totalLoan = totalPrincipal + totalInterest;
    const monthlyInstallment = Math.round(totalLoan / tenor);

    return {
        principal_vehicle: principalVehicle,
        insurance_rate_percent: insurance.rate * 100,
        insurance_premium: insurancePremium,
        total_principal: totalPrincipal,
        annual_interest_rate: ANNUAL_FLAT_INTEREST_RATE,
        total_interest: totalInterest,
        total_loan: totalLoan,
        monthly_installment: monthlyInstallment,
    };
}

