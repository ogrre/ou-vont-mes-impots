<?php

namespace Tests\Unit;

use App\Services\Api\HomePagePresenter;
use Tests\TestCase;

class HomePagePresenterTest extends TestCase
{
    public function test_it_presents_and_sorts_the_homepage_blocks(): void
    {
        $result = app(HomePagePresenter::class)->present($this->overview());

        $this->assertSame(2024, $result['data_year']);
        $this->assertSame(2024, $result['reference_year']);
        $this->assertSame('2024.1', $result['methodology_version']);
        $this->assertSame('120.00', $result['headline']['amount']);
        $this->assertSame('Où vont les dépenses publiques ?', $result['headline']['title']);
        $this->assertSame('Une vue d’ensemble des finances publiques françaises et du budget de l’État.', $result['headline']['description']);
        $this->assertSame('EUR', $result['headline']['unit']);
        $this->assertSame([], $result['headline']['items']);
        $this->assertNull($result['headline']['percentage']);
        $this->assertNull($result['headline']['per_100']);
        $this->assertSame('validated', $result['headline']['quality_status']);
        $this->assertSame(['source' => 'INSEE', 'source_url' => null, 'dataset' => 'public', 'source_page' => null], $result['headline']['provenance']);

        $this->assertSame(['large', 'small'], array_column($result['what_for']['items'], 'code'));
        $this->assertSame('50.00', $result['who_spends']['items'][0]['percentage']);
        $this->assertSame('50.00', $result['who_spends']['items'][0]['per_100']);
        $this->assertSame('EUR', $result['state_budget']['unit']);
        $this->assertSame('Budget de l’État', $result['state_budget']['title']);
        $this->assertSame('Budget', $result['state_budget']['provenance']['source']);
        $this->assertSame('validated', $result['revenues']['quality_status']);
        $this->assertSame('national_accounts', $result['revenues']['items'][0]['accounting_basis']);
        $this->assertSame('budgetary', $result['revenues']['items'][1]['accounting_basis']);
    }

    public function test_it_handles_missing_amounts_and_zero_denominators(): void
    {
        $overview = $this->overview();
        $overview['public_finances']['expenditure']['amount'] = null;
        $overview['public_finances']['quality'] = [];
        $overview['institutional_distribution'] = [
            'amount' => null,
            'denominator' => '0.00',
            'items' => [['code' => 'empty', 'amount' => null]],
            'quality' => [],
        ];
        $overview['functional_distribution'] = ['amount' => null, 'items' => [], 'quality' => []];
        $overview['state_budget'] = ['amount' => null, 'distribution' => [], 'quality' => []];
        $overview['revenues'] = [];

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertNull($result['public_spending']['amount']);
        $this->assertSame('not_importable', $result['public_spending']['quality_status']);
        $this->assertNull($result['who_spends']['items'][0]['percentage']);
        $this->assertNull($result['who_spends']['items'][0]['per_100']);
        $this->assertSame('not_importable', $result['revenues']['quality_status']);
        $this->assertNull($result['revenues']['items'][0]['amount']);
    }

    public function test_it_prioritizes_the_worst_revenue_quality_status(): void
    {
        $overview = $this->overview();
        $overview['revenues']['public_revenues']['quality'] = ['status' => 'review_required'];
        $overview['revenues']['state_budget_revenues']['quality'] = ['status' => 'validated'];

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertSame('review_required', $result['revenues']['quality_status']);
        $this->assertSame('review_required', $result['revenues']['quality']['status']);
    }

    public function test_it_returns_null_ratios_independently_for_missing_amount_or_denominator(): void
    {
        $overview = $this->overview();
        $overview['institutional_distribution']['denominator'] = '200.00';
        $overview['institutional_distribution']['items'] = [
            ['code' => 'missing_amount', 'amount' => null],
            ['code' => 'valid', 'amount' => '50.00'],
        ];
        $overview['functional_distribution']['items'] = [
            ['code' => 'missing_denominator', 'amount' => '50.00'],
        ];
        $overview['institutional_distribution']['items'][0]['amount'] = null;

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertNull($result['who_spends']['items'][0]['percentage']);
        $this->assertSame('25.00', $result['who_spends']['items'][1]['percentage']);

        $overview['institutional_distribution']['denominator'] = null;
        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertNull($result['who_spends']['items'][1]['percentage']);
    }

    public function test_it_normalizes_scalar_overview_sections_before_presenting_them(): void
    {
        $overview = $this->overview();
        $overview['year'] = '2024';
        $overview['public_finances']['expenditure'] = 'invalid';
        $overview['institutional_distribution'] = 'invalid';
        $overview['functional_distribution'] = 'invalid';
        $overview['state_budget'] = 'invalid';
        $overview['revenues'] = 'invalid';

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertSame(2024, $result['data_year']);
        $this->assertNull($result['public_spending']['amount']);
        $this->assertSame([], $result['who_spends']['items']);
        $this->assertSame([], $result['what_for']['items']);
        $this->assertSame([], $result['state_budget']['items']);
    }

    public function test_it_sorts_items_with_missing_amounts_last(): void
    {
        $overview = $this->overview();
        $overview['functional_distribution']['items'] = [
            ['code' => 'missing'],
            ['code' => 'small', 'amount' => '10.00'],
            ['code' => 'large', 'amount' => '100.00'],
        ];

        $result = app(HomePagePresenter::class)->present($overview);

        $this->assertSame(['large', 'small', 'missing'], array_column($result['what_for']['items'], 'code'));
    }

    /** @return array<string, mixed> */
    private function overview(): array
    {
        return [
            'year' => 2024,
            'public_finances' => [
                'expenditure' => ['amount' => '120.00'],
                'quality' => ['status' => 'validated'],
                'source' => 'INSEE',
                'dataset' => 'public',
            ],
            'institutional_distribution' => [
                'amount' => '100.00',
                'denominator' => '200.00',
                'items' => [['code' => 'half', 'amount' => '100.00']],
                'quality' => ['status' => 'validated'],
                'source' => 'INSEE',
            ],
            'functional_distribution' => [
                'amount' => '100.00',
                'items' => [
                    ['code' => 'small', 'amount' => '10.00'],
                    ['code' => 'large', 'amount' => '90.00'],
                ],
                'quality' => ['status' => 'validated'],
            ],
            'state_budget' => [
                'amount' => '80.00',
                'distribution' => [['code' => 'mission', 'amount' => '80.00']],
                'quality' => ['status' => 'validated'],
                'source' => 'Budget',
                'dataset' => 'state',
            ],
            'revenues' => [
                'public_revenues' => [
                    'amount' => '90.00',
                    'quality' => ['status' => 'validated'],
                    'accounting_basis' => 'national_accounts',
                    'source' => 'INSEE',
                ],
                'state_budget_revenues' => [
                    'amount' => '70.00',
                    'quality' => ['status' => 'validated'],
                    'accounting_basis' => 'budgetary',
                    'source' => 'Budget',
                ],
            ],
        ];
    }
}
