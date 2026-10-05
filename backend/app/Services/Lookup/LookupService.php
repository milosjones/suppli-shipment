<?php

namespace App\Services\Lookup;

use App\Models\CargoItem;
use App\Support\ContainerNumber;
use App\Support\TagNumber;
use Illuminate\Database\Eloquent\Builder;

final class LookupService
{
    public const TYPES = ['all', 'container', 'invoice', 'tag', 'package', 'product', 'customer'];

    public function search(string $query, string $type = 'all', int $limit = 200): LookupResult
    {
        $query = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $query));

        if (mb_strlen($query) < 2) {
            return new LookupResult($query, $type, collect(), false);
        }

        $like = '%'.$this->escape($query).'%';
        $tagKey = TagNumber::key($query);
        $containerNo = '%'.$this->escape(ContainerNumber::normalize($query)).'%';
        $wants = fn (string $candidate) => $type === 'all' || $type === $candidate;

        $items = CargoItem::query()
            ->select('cargo_items.*')
            ->join('containers', 'containers.id', '=', 'cargo_items.container_id')
            ->where(function (Builder $where) use ($wants, $like, $tagKey, $containerNo): void {
                if ($wants('container')) {
                    $where->orWhereLike('containers.container_no', $containerNo);
                }

                if ($wants('invoice')) {
                    $where->orWhereLike('cargo_items.proforma_invoice_no', $like)
                        ->orWhereLike('cargo_items.exporter_reference', $like);
                }

                if ($wants('tag') && $tagKey !== null) {
                    $where->orWhereLike('cargo_items.tag_key', '%'.$this->escape($tagKey).'%');
                }

                if ($wants('package')) {
                    $where->orWhereLike('cargo_items.package_no', $like);
                }

                if ($wants('product')) {
                    $where->orWhereLike('cargo_items.product_code', $like)
                        ->orWhereLike('cargo_items.product_description', $like)
                        ->orWhereLike('cargo_items.net_type', $like);
                }

                if ($wants('customer')) {
                    $where->orWhereLike('cargo_items.customer_name', $like)
                        ->orWhereLike('cargo_items.customer_po', $like);
                }
            })
            ->with('container.shipment')
            ->orderByDesc('containers.shipment_id')
            ->orderBy('containers.container_no')
            ->orderBy('cargo_items.package_no')
            ->limit($limit + 1)
            ->get();

        return new LookupResult($query, $type, $items->take($limit), $items->count() > $limit);
    }

    public function matchedFields(CargoItem $item, string $query): array
    {
        $tagKey = TagNumber::key($query);
        $candidates = [
            'container_no' => $item->container?->container_no,
            'proforma_invoice_no' => $item->proforma_invoice_no,
            'exporter_reference' => $item->exporter_reference,
            'package_no' => $item->package_no,
            'product_code' => $item->product_code,
            'product_description' => $item->product_description,
            'net_type' => $item->net_type,
            'customer_name' => $item->customer_name,
            'customer_po' => $item->customer_po,
        ];

        $matched = array_keys(array_filter(
            $candidates,
            fn (?string $value) => $value !== null && mb_stripos($value, $query) !== false,
        ));

        if ($tagKey !== null && $item->tag_key !== null && str_contains($item->tag_key, $tagKey)) {
            $matched[] = 'tag_number';
        }

        if ($item->container !== null && str_contains($item->container->container_no, ContainerNumber::normalize($query))) {
            $matched[] = 'container_no';
        }

        return array_values(array_unique($matched));
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
