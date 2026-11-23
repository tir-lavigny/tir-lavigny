<?php

namespace Tests\Unit\Services;

use App\Models\Member;
use App\Services\MemberExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private MemberExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MemberExportService;
    }

    public function test_default_columns_are_used_when_none_are_requested(): void
    {
        $this->assertSame(
            MemberExportService::DEFAULT_COLUMNS,
            $this->service->resolveColumns([], false),
        );
    }

    public function test_unknown_columns_are_dropped(): void
    {
        $columns = $this->service->resolveColumns(['last_name', 'password', 'first_name'], false);

        $this->assertSame(['last_name', 'first_name'], $columns);
    }

    public function test_requested_column_order_is_kept(): void
    {
        $columns = $this->service->resolveColumns(['status', 'last_name'], false);

        $this->assertSame(['status', 'last_name'], $columns);
    }

    public function test_sensitive_columns_are_dropped_without_permission(): void
    {
        $columns = $this->service->resolveColumns(['last_name', 'avs', 'is_swiss_citizen'], false);

        $this->assertSame(['last_name'], $columns);
    }

    public function test_sensitive_columns_are_kept_with_permission(): void
    {
        $columns = $this->service->resolveColumns(['last_name', 'avs'], true);

        $this->assertSame(['last_name', 'avs'], $columns);
    }

    public function test_headers_use_labels_and_append_extra_columns(): void
    {
        $headers = $this->service->headers(
            ['last_name', 'first_name'],
            [['header' => 'Is present', 'width' => 12.0], ['header' => 'Has paid', 'width' => null]],
        );

        $this->assertSame(['Nom', 'Prénom', 'Is present', 'Has paid'], $headers);
    }

    public function test_rows_contain_values_from_the_selected_columns(): void
    {
        Member::factory()->create([
            'first_name' => 'Lorem',
            'last_name' => 'Ipsum',
            'status' => 'passive',
        ]);

        $rows = iterator_to_array($this->service->rows(
            $this->service->memberQuery([]),
            ['first_name', 'last_name', 'status'],
            0,
        ));

        $this->assertCount(1, $rows);
        $this->assertSame(['Lorem', 'Ipsum', 'Passif'], $rows[0]);
    }

    public function test_rows_end_with_empty_extra_columns(): void
    {
        Member::factory()->create(['first_name' => 'Lorem']);

        $rows = iterator_to_array($this->service->rows(
            $this->service->memberQuery([]),
            ['first_name'],
            2,
        ));

        $this->assertSame(['Lorem', '', ''], $rows[0]);
    }

    public function test_rows_expose_the_decrypted_avs_when_selected(): void
    {
        Member::factory()->withAvs('756.1234.5678.90')->create();

        $rows = iterator_to_array($this->service->rows(
            $this->service->memberQuery([]),
            ['avs', 'is_swiss_citizen'],
            0,
        ));

        $this->assertSame(['756.1234.5678.90', 'Oui'], $rows[0]);
    }

    public function test_rows_honour_the_status_filter(): void
    {
        Member::factory()->create(['last_name' => 'Actif']);
        Member::factory()->passive()->create(['last_name' => 'Passif']);

        $rows = iterator_to_array($this->service->rows(
            $this->service->memberQuery(['status' => 'passive']),
            ['last_name'],
            0,
        ));

        $this->assertSame([['Passif']], $rows);
    }

    public function test_rows_honour_the_search_filter(): void
    {
        Member::factory()->create(['last_name' => 'Dupont']);
        Member::factory()->create(['last_name' => 'Martin']);

        $rows = iterator_to_array($this->service->rows(
            $this->service->memberQuery(['search' => 'dup']),
            ['last_name'],
            0,
        ));

        $this->assertSame([['Dupont']], $rows);
    }

    public function test_rows_exclude_archived_members_unless_requested(): void
    {
        Member::factory()->create(['last_name' => 'Actuel']);
        Member::factory()->archived()->create(['last_name' => 'Archive']);

        $current = iterator_to_array($this->service->rows($this->service->memberQuery([]), ['last_name'], 0));
        $archived = iterator_to_array($this->service->rows($this->service->memberQuery(['archived' => true]), ['last_name'], 0));

        $this->assertSame([['Actuel']], $current);
        $this->assertSame([['Archive']], $archived);
    }

    public function test_status_sorting_groups_active_members_first(): void
    {
        Member::factory()->passive()->create(['last_name' => 'Passif']);
        Member::factory()->create(['last_name' => 'Actif']);

        $rows = iterator_to_array($this->service->rows(
            $this->service->memberQuery(['sort' => 'status']),
            ['status'],
            0,
        ));

        $this->assertSame([['Actif'], ['Passif']], $rows);
    }

    public function test_column_widths_fit_the_widest_value_in_the_selected_order(): void
    {
        Member::factory()->create([
            'first_name' => 'Maximilien',
            'last_name' => 'Dupont',
            'email' => 'maximilien.dupont@example.com',
        ]);

        $widths = $this->service->columnWidths(
            $this->service->memberQuery([]),
            ['last_name', 'first_name', 'email'],
            [],
        );

        // 1-based, in the requested order: "Dupont" 6, "Maximilien" 10, the email 29,
        // each plus WIDTH_PADDING.
        $this->assertSame([1 => 8.0, 2 => 12.0, 3 => 31.0], $widths);
    }

    public function test_column_widths_fall_back_to_the_header_when_there_are_no_rows(): void
    {
        $widths = $this->service->columnWidths($this->service->memberQuery([]), ['last_name', 'email'], []);

        // "Nom" 3 and "Email" 5, both padded to 5 and 7, then raised to MIN_WIDTH.
        $this->assertSame([1 => 8.0, 2 => 8.0], $widths);
    }

    public function test_column_widths_are_clamped_to_the_maximum(): void
    {
        Member::factory()->create(['last_name' => str_repeat('a', 200)]);

        $widths = $this->service->columnWidths($this->service->memberQuery([]), ['last_name'], []);

        $this->assertSame([1 => MemberExportService::MAX_WIDTH], $widths);
    }

    public function test_extra_columns_get_fitted_widths_and_keep_explicit_ones(): void
    {
        $widths = $this->service->columnWidths(
            $this->service->memberQuery([]),
            ['last_name'],
            [
                ['header' => 'Is present', 'width' => 12],
                ['header' => 'Has paid'],
                ['header' => 'Remarks', 'width' => '30'],
            ],
        );

        // Member columns first, then the extras at 2, 3 and 4.
        $this->assertSame(8.0, $widths[1]);
        $this->assertSame(12.0, $widths[2]);
        $this->assertSame(10.0, $widths[3]);  // "Has paid" 8 + 2
        $this->assertSame(30.0, $widths[4]);
    }
}
