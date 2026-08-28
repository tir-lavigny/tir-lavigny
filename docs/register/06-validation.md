# Register System — Validation Rules & Form Requests

## Form Request Organization

```
app/Http/Requests/
├── API/
│   └── Register/
│       ├── OpenDayRequest.php
│       ├── CloseDayRequest.php
│       ├── AddCashRemarkRequest.php
│       ├── StoreTicketRequest.php
│       └── VoucherValidateRequest.php
└── BackOffice/
    └── Register/
        ├── StoreProductRequest.php
        ├── UpdateProductRequest.php
        ├── StoreCategoryRequest.php
        ├── UpdateCategoryRequest.php
        ├── StoreVoucherRequest.php
        ├── UpdateVoucherRequest.php
        ├── StoreSeasonRequest.php
        ├── UpdateSeasonRequest.php
        ├── StoreMemberRequest.php
        ├── UpdateMemberRequest.php
        └── UpdateOperatingDayRequest.php
```

All requests extend `Illuminate\Foundation\Http\FormRequest` and implement `authorize()` to check the relevant permission.

---

## API Form Requests

### OpenDayRequest

```php
'season_id' => ['required', 'exists:seasons,id,deleted_at,NULL'],
```

**Custom rules:**
- `NoOpenDayExists` — No operating day with `closed_at IS NULL` for the same season.
- `DateNotDuplicate` — No operating day with same `date` for the same season.

### CloseDayRequest

```php
'closing_balance' => ['nullable', 'numeric', 'min:0'],
```

- Day must be open (validated in controller logic).

### AddCashRemarkRequest

```php
'cash_remark' => ['required', 'string', 'max:5000'],
```

### StoreTicketRequest

```php
'payment_method'   => ['required', 'string', Rule::in(['cash', 'twint'])],
'operating_day_id' => ['required', 'exists:operating_days,id'],
'items'            => ['required', 'array', 'min:1'],
'items.*.product_id' => ['required', 'exists:products,id,deleted_at,NULL'],
'items.*.quantity'    => ['required', 'integer', 'min:1'],
'items.*.custom_unit_price' => [
    'nullable',
    'numeric',
    'min:0',
    new RequiredIfProductAllowsCustomPrice(), // custom rule
],
'items.*.voucher_code' => ['nullable', 'string', 'exists:vouchers,code,deleted_at,NULL'],
```

**Custom rules:**
- `RequiredIfProductAllowsCustomPrice` — If the product `allows_custom_price` is true, this field is required.

### VoucherValidateRequest

```php
'code'       => ['required', 'string', 'exists:vouchers,code,deleted_at,NULL'],
'product_id' => ['required', 'exists:products,id,deleted_at,NULL'],
'season_id'  => ['required', 'exists:seasons,id,deleted_at,NULL'],
```

**Custom rules:**
- `VoucherIsUsable` — Code exists, not used (`!$voucher->isUsed()`), belongs to product, belongs to season, season is active (today between season start/end).

---

## Back-Office Form Requests

### StoreProductRequest

```php
'name'                => ['required', 'string', 'max:255'],
'picture'             => ['nullable', 'image', 'max:2048'],
'unit_price'          => ['required', 'numeric', 'min:0'],
'allows_custom_price' => ['boolean'],
'category_id'         => ['required', 'exists:categories,id,deleted_at,NULL'],
```

### UpdateProductRequest

```php
'name'                => ['sometimes', 'string', 'max:255'],
'picture'             => ['nullable', 'image', 'max:2048'],
'unit_price'          => ['sometimes', 'numeric', 'min:0'],
'allows_custom_price' => ['boolean'],
'category_id'         => ['sometimes', 'exists:categories,id,deleted_at,NULL'],
```

### StoreCategoryRequest

```php
'name'          => ['required', 'string', 'max:255', Rule::unique('categories')],
'is_restricted' => ['boolean'],
```

### UpdateCategoryRequest

```php
'name'          => ['sometimes', 'string', 'max:255', Rule::unique('categories')->ignore($this->route('category'))],
'is_restricted' => ['boolean'],
```

### StoreVoucherRequest

```php
'code'             => ['required', 'string', 'max:255', Rule::unique('vouchers')],
'discount_percent' => ['required_without:discount_fixed', 'nullable', 'numeric', 'between:0,100'],
'discount_fixed'   => ['required_without:discount_percent', 'nullable', 'numeric', 'min:0'],
'product_id'       => ['required', 'exists:products,id,deleted_at,NULL'],
'season_id'        => ['required', 'exists:seasons,id,deleted_at,NULL'],
'member_id'        => ['required', 'exists:members,id,deleted_at,NULL'],
```

**Custom rules:**
- `OnlyOneDiscountType` — Ensures only one of `discount_percent` or `discount_fixed` is provided (not both, not neither).

### UpdateVoucherRequest

```php
'code'             => ['sometimes', 'string', 'max:255', Rule::unique('vouchers')->ignore($this->route('voucher'))],
'discount_percent' => ['required_without:discount_fixed', 'nullable', 'numeric', 'between:0,100'],
'discount_fixed'   => ['required_without:discount_percent', 'nullable', 'numeric', 'min:0'],
'product_id'       => ['sometimes', 'exists:products,id,deleted_at,NULL'],
'season_id'        => ['sometimes', 'exists:seasons,id,deleted_at,NULL'],
'member_id'        => ['sometimes', 'exists:members,id,deleted_at,NULL'],
```

- Only-one-discount rule still applies.

### StoreSeasonRequest

```php
'name'                 => ['required', 'string', 'max:255'],
'start_date'           => ['required', 'date'],
'end_date'             => ['required', 'date', 'after_or_equal:start_date'],
'initial_balance'      => ['required', 'numeric', 'min:0'],
'end_balance'          => ['nullable', 'numeric', 'min:0'],
```

### UpdateSeasonRequest

```php
'name'                 => ['sometimes', 'string', 'max:255'],
'start_date'           => ['sometimes', 'date'],
'end_date'             => ['sometimes', 'date', 'after_or_equal:start_date'],
'initial_balance'      => ['sometimes', 'numeric', 'min:0'],
'end_balance'          => ['nullable', 'numeric', 'min:0'],
```

### StoreMemberRequest

```php
'first_name'    => ['required', 'string', 'max:255'],
'last_name'     => ['required', 'string', 'max:255'],
'member_number' => ['required', 'string', 'max:255', Rule::unique('members')],
```

### UpdateMemberRequest

```php
'first_name'    => ['sometimes', 'string', 'max:255'],
'last_name'     => ['sometimes', 'string', 'max:255'],
'member_number' => ['sometimes', 'string', 'max:255', Rule::unique('members')->ignore($this->route('member'))],
```

### UpdateOperatingDayRequest

```php
'cash_remark'     => ['nullable', 'string', 'max:5000'],
'closing_balance' => ['nullable', 'numeric', 'min:0'],
```
