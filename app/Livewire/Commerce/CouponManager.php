<?php

namespace App\Livewire\Commerce;

use App\Models\Coupon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CouponManager extends Component
{
    use WithPagination;

    public ?string $couponId = null;

    public string $code = '';

    public string $discountType = 'percentage';

    public string $discountValue = '';

    public ?string $maxUses = null;

    public ?string $validUntil = null;

    public bool $isActive = true;

    public function edit(string $id): void
    {
        $this->assertAdmin();
        $coupon = Coupon::findOrFail($id);
        $this->couponId = $coupon->id;
        $this->code = $coupon->code;
        $this->discountType = $coupon->discount_type;
        $this->discountValue = (string) $coupon->discount_value;
        $this->maxUses = $coupon->max_uses === null ? null : (string) $coupon->max_uses;
        $this->validUntil = $coupon->valid_until?->format('Y-m-d\TH:i');
        $this->isActive = $coupon->is_active;
    }

    public function save(): void
    {
        $this->assertAdmin();
        $this->code = strtoupper(trim($this->code));
        $this->validate([
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($this->couponId)],
            'discountType' => 'required|in:percentage,fixed_amount',
            'discountValue' => 'required|numeric|min:0.01|max:'.($this->discountType === 'percentage' ? '100' : '9999999999999'),
            'maxUses' => 'nullable|integer|min:1|max:4294967295', 'validUntil' => 'nullable|date', 'isActive' => 'boolean',
        ]);
        DB::transaction(function () {
            $coupon = $this->couponId ? Coupon::whereKey($this->couponId)->lockForUpdate()->firstOrFail() : new Coupon;
            $reserved = $coupon->exists ? $coupon->redemptions()->where('status', 'reserved')->lockForUpdate()->get(['id'])->count() : 0;
            if (filled($this->maxUses) && (int) $this->maxUses < (int) $coupon->used_count + $reserved) {
                throw ValidationException::withMessages(['maxUses' => 'Kuota tidak boleh lebih kecil dari pemakaian dan reservasi yang ada.']);
            }
            $coupon->fill([
                'code' => $this->code, 'discount_type' => $this->discountType, 'discount_value' => $this->discountValue,
                'max_uses' => filled($this->maxUses) ? $this->maxUses : null,
                'valid_until' => filled($this->validUntil) ? $this->validUntil : null, 'is_active' => $this->isActive,
            ])->save();
        }, 3);
        $this->clearForm();
        session()->flash('commerceStatus', 'Kupon disimpan. Reservasi invoice sebelumnya tetap dihormati.');
    }

    public function delete(string $id): void
    {
        $this->assertAdmin();
        DB::transaction(function () use ($id) {
            $coupon = Coupon::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($coupon->redemptions()->exists()) {
                $coupon->update(['is_active' => false]);
            } else {
                $coupon->delete();
            }
        }, 3);
        $this->clearForm();
        session()->flash('commerceStatus', 'Kupon dihapus atau dinonaktifkan untuk menjaga riwayat transaksi.');
    }

    public function clearForm(): void
    {
        $this->assertAdmin();
        $this->reset('couponId', 'code', 'discountType', 'discountValue', 'maxUses', 'validUntil', 'isActive');
        $this->resetValidation();
    }

    private function assertAdmin(): void
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);
    }

    public function render()
    {
        $this->assertAdmin();

        return view('livewire.commerce.coupon-manager', ['coupons' => Coupon::withCount(['redemptions as reserved_count' => fn ($query) => $query->where('status', 'reserved')])->latest()->paginate(15)]);
    }
}
