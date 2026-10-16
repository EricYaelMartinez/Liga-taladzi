import { existsSync, readFileSync } from 'node:fs';

const required = [
    'database/migrations/2026_10_14_000020_create_finance_tables.php',
    'app/Domain/Finance/Models/FinanceConcept.php',
    'app/Domain/Finance/Models/FinanceCharge.php',
    'app/Domain/Finance/Models/FinancePayment.php',
    'app/Domain/Finance/Services/FinanceService.php',
    'app/Console/Commands/SyncFinanceLifecycle.php',
    'app/Http/Controllers/League/FinanceController.php',
    'app/Http/Requests/StoreBulkFinanceChargeRequest.php',
    'resources/js/pages/League/Finance/Index.vue',
    'resources/views/finance/receipt.blade.php',
    'tests/Feature/League/FinanceManagementTest.php',
    'MODULO_15_VERIFICACION.md',
];
for (const file of required) if (!existsSync(file)) throw new Error(`Falta el archivo requerido: ${file}`);
const migration = readFileSync(required[0], 'utf8');
for (const table of ['finance_concepts', 'finance_charges', 'finance_payments', 'finance_payment_allocations', 'finance_receipt_sequences']) if (!migration.includes(table)) throw new Error(`Falta la estructura financiera: ${table}`);
const service = readFileSync('app/Domain/Finance/Services/FinanceService.php', 'utf8');
for (const rule of ['createBulkCharges', 'registerPayment', 'voidPayment', 'syncObligations', 'evaluateAll', 'evaluateLeague', "'grace'", "'suspended'", "'excluded'", 'nextReceiptNumber']) if (!service.includes(rule)) throw new Error(`Falta la regla financiera: ${rule}`);
const routes = readFileSync('routes/web.php', 'utf8');
for (const route of ["'/finanzas'", "'/finanzas/adeudos'", "'/finanzas/adeudos/masivos'", "'/finanzas/pagos'", "'/pagos/{payment}/recibo.pdf'", "'/finanzas/sincronizar'"]) if (!routes.includes(route)) throw new Error(`Falta la ruta financiera: ${route}`);
const view = readFileSync('resources/js/pages/League/Finance/Index.vue', 'utf8');
for (const element of ['Pagos, fianzas y recibos', 'Varios equipos', 'Total proyectado', 'closedCompetitionStatuses', 'Registrar pago en efectivo', 'Estado de cuenta', 'Descargar PDF']) if (!view.includes(element)) throw new Error(`Falta el control visual: ${element}`);
console.log('Validación estructural del Módulo 15 completada.');
