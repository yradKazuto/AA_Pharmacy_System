<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\RoleMiddleware;
use App\Models\Product;

/**
 * CustomerCatalogController — the online shop browse view.
 * Gated to customers (catalog:view).
 */
class CustomerCatalogController extends Controller
{
    /**
     * Browse the live catalog — available (in-stock) products only.
     *
     * @return void
     */
    public function index()
    {
        RoleMiddleware::requirePermission('catalog:view', '/unauthorized');

        $productModel = new Product();
        $products = $productModel->getAllWithStock();

        // Only show products we could actually fulfill (stock > 0).
        $products = array_filter($products, function ($p) {
            return (int)$p['total_qty'] > 0;
        });

        $this->view('catalog/index', ['products' => array_values($products)]);
    }
}
