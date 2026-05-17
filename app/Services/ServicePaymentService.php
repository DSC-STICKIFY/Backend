<?php

namespace App\Services;

use App\Models\ServicePaymentModel;

class ServicePaymentService
{
    public function getAll()
    {
        return ServicePaymentModel::with(['service', 'employee', 'product'])->get();
    }

    public function create(array $data)
    {
        $data['invoice'] = $this->generateAutomatedInvoice();

        return ServicePaymentModel::create($data);
    }

    // Automated Invoice (Business Logic)
    private function generateAutomatedInvoice()
    {
        $datepart = now()->format('Y-m-d');
        $invoiceNumber = mt_rand(1000, 99999);

        return "INV-{$datepart}-{$invoiceNumber}";
    }

    public function getById($id)
    {
        return ServicePaymentModel::with(['service', 'employee', 'product'])->find($id);
    }

    public function update($id, array $data)
    {
        $payment = ServicePaymentModel::find($id);

        if (! $payment) {
            return null;
        }

        $payment->update($data);

        return $payment;
    }

    public function delete($id)
    {
        $payment = ServicePaymentModel::find($id);

        if (! $payment) {
            return false;
        }

        $payment->delete();

        return true;
    }

    public function getByService($id)
    {
        return ServicePaymentModel::with(['service', 'employee', 'product'])
            ->where('service_id', $id)
            ->get();
    }
}
