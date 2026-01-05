<?php

namespace App\Http\Requests\Debt;

use App\Enums\DebtType;
use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DebtPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|gt:0',
            'date' => 'required|date',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->validateAccountIsNotDebt($validator);
                $this->validateSufficientFunds($validator);
                $this->validateNotOverpaying($validator);
            },
        ];
    }

    private function validateAccountIsNotDebt(Validator $validator): void
    {
        $account = Account::find($this->input('account_id'));
        if ($account && $account->isDebt()) {
            $validator->errors()->add('account_id', 'Cannot use debt account for payments.');
        }
    }

    private function validateSufficientFunds(Validator $validator): void
    {
        $debt = $this->route('debt');

        if (!$debt || !$debt->isDebt()) {
            return;
        }

        // For "i_owe" type, check source account balance
        if ($debt->debt_type === DebtType::IOwe) {
            $account = Account::find($this->input('account_id'));
            if ($account && $account->current_balance < $this->input('amount')) {
                $validator->errors()->add(
                    'amount',
                    'Insufficient funds. Available: '.number_format($account->current_balance, 2)
                );
            }
        }
    }

    private function validateNotOverpaying(Validator $validator): void
    {
        $debt = $this->route('debt');

        if (!$debt || !$debt->isDebt()) {
            return;
        }

        $amount = (float) $this->input('amount');
        $remainingDebt = $debt->current_balance;

        if ($amount > $remainingDebt) {
            $validator->errors()->add(
                'amount',
                "Payment amount ({$amount}) exceeds remaining debt ({$remainingDebt})."
            );
        }
    }
}
