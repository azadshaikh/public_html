<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

/**
 * HasAlerts Trait
 *
 * This trait provides helper methods for generating rich alert messages
 * that can be used with the enhanced alert component.
 *
 * USAGE:
 * ------
 * 1. Add the trait to your controller:
 *    use App\Traits\HasAlerts;
 *
 * 2. Use the helper methods in your controller actions:
 *    return $this->redirectWithSuccess('Product created!', $product);
 *    return $this->redirectWithError('Something went wrong', $errors);
 *    return $this->redirectWithWarning('Please review these items', $warnings);
 *    return $this->redirectWithInfo('FYI: Process completed', $data);
 *
 * ADVANCED USAGE:
 * --------------
 * All methods accept additional parameters for rich content:
 *
 * return $this->redirectWithSuccess(
 *     title: 'Product Created!',
 *     message: 'Your product is now available',
 *     redirectTo: route('products.show', $product),
 *     data: ['sku' => $product->sku, 'price' => $product->price],
 *     actions: [
 *         ['label' => 'View', 'url' => route('products.show', $product), 'class' => 'btn-primary']
 *     ],
 *     html: '<strong>Custom HTML content</strong>'
 * );
 */
trait HasAlerts
{
    /**
     * Redirect with success alert
     *
     * @param  string  $title  Alert title
     * @param  string|null  $message  Alert message
     * @param  string|null  $redirectTo  Redirect URL (defaults to back)
     * @param  array  $data  Additional data to display as badges
     * @param  array  $actions  Action buttons array
     * @param  string|null  $html  Custom HTML content
     * @param  array  $list  List items to display
     * @param  string|null  $icon  Custom icon class
     * @return RedirectResponse
     */
    public function redirectWithSuccess(
        string $title,
        ?string $message = null,
        ?string $redirectTo = null,
        array $data = [],
        array $actions = [],
        ?string $html = null,
        array $list = [],
        ?string $icon = null
    ) {
        return $this->redirectWithAlert('success', $title, $message, $redirectTo, $data, $actions, $html, $list, $icon);
    }

    /**
     * Redirect with error alert
     *
     * @param  string  $title  Alert title
     * @param  string|null  $message  Alert message
     * @param  string|null  $redirectTo  Redirect URL (defaults to back)
     * @param  array  $data  Additional data to display as badges
     * @param  array  $actions  Action buttons array
     * @param  string|null  $html  Custom HTML content
     * @param  array  $list  List items to display
     * @param  string|null  $icon  Custom icon class
     * @return RedirectResponse
     */
    public function redirectWithError(
        string $title,
        ?string $message = null,
        ?string $redirectTo = null,
        array $data = [],
        array $actions = [],
        ?string $html = null,
        array $list = [],
        ?string $icon = null
    ) {
        return $this->redirectWithAlert('error', $title, $message, $redirectTo, $data, $actions, $html, $list, $icon);
    }

    /**
     * Redirect with warning alert
     *
     * @param  string  $title  Alert title
     * @param  string|null  $message  Alert message
     * @param  string|null  $redirectTo  Redirect URL (defaults to back)
     * @param  array  $data  Additional data to display as badges
     * @param  array  $actions  Action buttons array
     * @param  string|null  $html  Custom HTML content
     * @param  array  $list  List items to display
     * @param  string|null  $icon  Custom icon class
     * @return RedirectResponse
     */
    public function redirectWithWarning(
        string $title,
        ?string $message = null,
        ?string $redirectTo = null,
        array $data = [],
        array $actions = [],
        ?string $html = null,
        array $list = [],
        ?string $icon = null
    ) {
        return $this->redirectWithAlert('warning', $title, $message, $redirectTo, $data, $actions, $html, $list, $icon);
    }

    /**
     * Redirect with info alert
     *
     * @param  string  $title  Alert title
     * @param  string|null  $message  Alert message
     * @param  string|null  $redirectTo  Redirect URL (defaults to back)
     * @param  array  $data  Additional data to display as badges
     * @param  array  $actions  Action buttons array
     * @param  string|null  $html  Custom HTML content
     * @param  array  $list  List items to display
     * @param  string|null  $icon  Custom icon class
     * @return RedirectResponse
     */
    public function redirectWithInfo(
        string $title,
        ?string $message = null,
        ?string $redirectTo = null,
        array $data = [],
        array $actions = [],
        ?string $html = null,
        array $list = [],
        ?string $icon = null
    ) {
        return $this->redirectWithAlert('info', $title, $message, $redirectTo, $data, $actions, $html, $list, $icon);
    }

    /**
     * Generate a simple alert (backward compatibility)
     *
     * @param  string  $type  Alert type
     * @param  string  $message  Simple message
     * @param  string|null  $redirectTo  Redirect URL
     * @return RedirectResponse
     */
    public function redirectWithSimpleAlert(string $type, string $message, ?string $redirectTo = null)
    {
        $redirect = $redirectTo ? redirect($redirectTo) : back();

        return $redirect->with($type, $message);
    }

    /**
     * Helper for creating product-related success alerts
     *
     * @param  Model  $product  Product model
     * @param  string  $action  Action performed (created, updated, deleted, etc.)
     * @param  string|null  $redirectTo  Redirect URL
     * @param  array  $additionalData  Additional data to display
     * @return RedirectResponse
     */
    public function redirectWithProductSuccess($product, string $action, ?string $redirectTo = null, array $additionalData = [])
    {
        $titles = [
            'created' => 'Product Created Successfully!',
            'updated' => 'Product Updated Successfully!',
            'deleted' => 'Product Deleted Successfully!',
            'restored' => 'Product Restored Successfully!',
        ];

        $messages = [
            'created' => 'Your product has been saved and is now available.',
            'updated' => 'All changes have been saved successfully.',
            'deleted' => 'The product has been moved to trash.',
            'restored' => 'The product has been restored and is now available.',
        ];

        $data = array_merge([
            'product_name' => $product->name ?? 'N/A',
            'sku' => $product->sku ?? 'N/A',
        ], $additionalData);

        if (isset($product->created_at)) {
            $data['created_at'] = $product->created_at->format('M d, Y g:i A');
        }

        if (isset($product->price)) {
            $data['price'] = '₹'.number_format($product->price, 2);
        }

        $actions = [];
        if ($action !== 'deleted') {
            $actions[] = [
                'label' => 'View Product',
                'url' => route('samplemodule.products.show', $product->getRouteKey()),
                'class' => 'btn-primary btn-sm',
                'icon' => 'ri-eye-line',
            ];
        }

        return $this->redirectWithSuccess(
            title: $titles[$action] ?? 'Action Completed!',
            message: $messages[$action] ?? 'The action was completed successfully.',
            redirectTo: $redirectTo,
            data: $data,
            actions: $actions
        );
    }

    /**
     * Helper for creating bulk operation alerts
     *
     * @param  string  $operation  Operation performed
     * @param  int  $successCount  Number of successful operations
     * @param  int  $totalCount  Total number of items
     * @param  array  $errors  Array of errors
     * @param  string|null  $redirectTo  Redirect URL
     * @return RedirectResponse
     */
    public function redirectWithBulkOperationAlert(
        string $operation,
        int $successCount,
        int $totalCount,
        array $errors = [],
        ?string $redirectTo = null
    ) {
        $failedCount = $totalCount - $successCount;

        $operationLabels = [
            'delete' => 'deleted',
            'restore' => 'restored',
            'update' => 'updated',
            'export' => 'exported',
            'import' => 'imported',
        ];

        $operationLabel = $operationLabels[$operation] ?? $operation;
        if ($successCount === $totalCount) {
            // Complete success
            return $this->redirectWithSuccess(
                title: 'Bulk Operation Completed!',
                message: sprintf('All %d item(s) were %s successfully.', $totalCount, $operationLabel),
                redirectTo: $redirectTo,
                data: [
                    'operation' => ucfirst($operationLabel),
                    'success_count' => $successCount,
                    'total_count' => $totalCount,
                ]
            );
        }

        if ($successCount > 0) {
            // Partial success
            $list = [
                sprintf('✓ %d item(s) %s successfully', $successCount, $operationLabel),
                sprintf('✗ %d item(s) failed', $failedCount),
            ];
            if ($errors !== []) {
                $list[] = 'View details below for error information';
            }

            return $this->redirectWithWarning(
                title: 'Bulk Operation Partially Completed',
                message: "Some items couldn't be processed.",
                redirectTo: $redirectTo,
                list: $list,
                data: [
                    'operation' => ucfirst($operationLabel),
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                    'success_rate' => round($successCount / $totalCount * 100, 1).'%',
                ]
            );
        }

        // Complete failure
        return $this->redirectWithError(
            title: 'Bulk Operation Failed',
            message: sprintf('No items could be %s. Please check the errors and try again.', $operationLabel),
            redirectTo: $redirectTo,
            data: [
                'operation' => ucfirst($operationLabel),
                'total_count' => $totalCount,
                'error_count' => count($errors),
            ]
        );
    }

    /**
     * Helper for validation error alerts
     *
     * @param  array  $errors  Validation errors
     * @param  string|null  $redirectTo  Redirect URL
     * @return RedirectResponse
     */
    public function redirectWithValidationErrors(array $errors, ?string $redirectTo = null)
    {
        $errorList = [];
        foreach ($errors as $fieldErrors) {
            $errorList[] = is_array($fieldErrors) ? implode(', ', $fieldErrors) : $fieldErrors;
        }

        return $this->redirectWithError(
            title: 'Validation Failed',
            message: 'Please correct the following errors and try again:',
            redirectTo: $redirectTo,
            list: $errorList,
            data: [
                'error_count' => count($errorList),
                'fields_with_errors' => count($errors),
            ]
        );
    }

    /**
     * Base method for redirecting with alerts
     *
     * @param  string  $type  Alert type (success, error, warning, info)
     * @param  string  $title  Alert title
     * @param  string|null  $message  Alert message
     * @param  string|null  $redirectTo  Redirect URL
     * @param  array  $data  Additional data
     * @param  array  $actions  Action buttons
     * @param  string|null  $html  Custom HTML
     * @param  array  $list  List items
     * @param  string|null  $icon  Custom icon
     * @return RedirectResponse
     */
    protected function redirectWithAlert(
        string $type,
        string $title,
        ?string $message = null,
        ?string $redirectTo = null,
        array $data = [],
        array $actions = [],
        ?string $html = null,
        array $list = [],
        ?string $icon = null
    ) {
        $alertData = ['title' => $title];

        if ($message) {
            $alertData['message'] = $message;
        }

        if ($html) {
            $alertData['html'] = $html;
        }

        if ($data !== []) {
            $alertData['data'] = $data;
        }

        if ($actions !== []) {
            $alertData['actions'] = $actions;
        }

        if ($list !== []) {
            $alertData['list'] = $list;
        }

        if ($icon) {
            $alertData['icon'] = $icon;
        }

        $redirect = $redirectTo ? redirect($redirectTo) : back();

        return $redirect->with($type, $alertData);
    }
}
