<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Payments
 * APIs for managing payment installments and tracking debt.
 */
class PaymentController extends Controller
{
    /**
     * List All Payments
     * 
     * Get paginated list of all payment installments across sales and purchases.
     * 
     * @queryParam type string optional Filter by transaction type: 'sale' or 'purchase'. Example: sale
     * @queryParam from_date string optional Filter by start date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam to_date string optional Filter by end date (YYYY-MM-DD). Example: 2024-12-31
     * @queryParam search string optional Search by payment_method or invoice_number. Example: cash
     * @queryParam limit integer optional Items per page. Default: 10. Example: 20
     * @queryParam page integer optional Page number. Default: 1. Example: 2
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $type = $request->query('type'); // sale or purchase
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $query = Payment::with('payable');

        if ($type) {
            $payableType = $type === 'sale' ? Sale::class : Purchase::class;
            $query->where('payable_type', $payableType);
        }

        if ($fromDate) {
            $query->whereDate('payment_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('payment_date', '<=', $toDate);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_method', 'like', "%{$search}%")
                  ->orWhereHasMorph('payable', [Sale::class, Purchase::class], function ($query) use ($search) {
                      $query->where('invoice_number', 'like', "%{$search}%");
                  });
            });
        }

        $payments = $query->orderBy('payment_date', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->paginate($limit);

        return response()->json([
            'message' => 'Payments fetched successfully',
            'data' => $payments->items(),
            'pagination' => [
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
            ]
        ]);
    }

    /**
     * List All Payables (All transactions for payment management)
     * 
     * Get a combined list of Sales and Purchases across all payment statuses (paid, unpaid, partial).
     * 
     * @queryParam type string optional Filter by transaction type: 'sale' or 'purchase'. Example: sale
     * @queryParam from_date string optional Filter by start date (YYYY-MM-DD). Example: 2024-01-01
     * @queryParam to_date string optional Filter by end date (YYYY-MM-DD). Example: 2024-12-31
     * @queryParam payment_status string optional Filter by payment status: 'paid', 'partial', or 'unpaid'. Example: unpaid
     * @queryParam limit integer optional Items per page. Default: 10. Example: 20
     * @queryParam page integer optional Page number. Default: 1. Example: 2
     */
    public function payables(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        $page = (int) $request->query('page', 1);
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $type = $request->query('type'); // optional: sale|purchase
        $status = $request->query('payment_status'); // optional: paid|partial|unpaid

        $sales = collect();
        if (!$type || $type === 'sale') {
            $query = Sale::query()->with('customer');
            if ($fromDate) $query->whereDate('sale_date', '>=', $fromDate);
            if ($toDate) $query->whereDate('sale_date', '<=', $toDate);
            if ($status) $query->where('payment_status', $status);
            
            $sales = $query->get()->map(function ($item) {
                $item->type = 'sale';
                return $item;
            });
        }

        $purchases = collect();
        if (!$type || $type === 'purchase') {
            $query = Purchase::query()->with('supplier');
            if ($fromDate) $query->whereDate('purchase_date', '>=', $fromDate);
            if ($toDate) $query->whereDate('purchase_date', '<=', $toDate);
            if ($status) $query->where('payment_status', $status);

            $purchases = $query->get()->map(function ($item) {
                $item->type = 'purchase';
                return $item;
            });
        }

        $combined = $sales->concat($purchases)->sortByDesc('created_at')->values();

        // Manual Pagination
        $offset = ($page - 1) * $limit;
        $items = $combined->slice($offset, $limit)->values();
        
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $combined->count(),
            $limit,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'message' => 'Payables fetched successfully',
            'data' => $paginated->items(),
            'pagination' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
            ]
        ]);
    }

    /**
     * Store a Payment
     * 
     * Record a new payment installment for a Sale or Purchase.
     */
    public function store(Request $request)
    {
        $request->validate([
            'payable_type' => 'required|string|in:sale,purchase',
            'payable_id' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'nullable|date',
            'payment_method' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $payableType = $request->payable_type === 'sale' ? Sale::class : Purchase::class;
        $payable = $payableType::findOrFail($request->payable_id);

        // check if already fully paid
        if ($payable->payment_status === 'paid' && $payable->due_amount <= 0) {
             return response()->json(['message' => 'This transaction is already fully paid.'], 422);
        }

        // restrict overpayment
        if ($request->amount > $payable->due_amount) {
            return response()->json([
                'message' => 'Payment amount (' . $request->amount . ') exceeds the due amount (' . $payable->due_amount . ').'
            ], 422);
        }

        return DB::transaction(function () use ($request, $payable, $payableType) {
            $payment = Payment::create([
                'payable_type' => $payableType,
                'payable_id' => $payable->id,
                'amount' => $request->amount,
                'payment_date' => $request->payment_date ?? now(),
                'payment_method' => $request->payment_method,
                'notes' => $request->notes,
            ]);

            // Update payable amounts
            $payable->paid_amount += $request->amount;
            $payable->due_amount = $payable->grand_total - $payable->paid_amount;
            
            if ($payable->due_amount <= 0) {
                $payable->payment_status = 'paid';
                $payable->due_amount = 0; // handle overpayment if any
            } else {
                $payable->payment_status = 'partial';
            }

            $payable->save();

            return response()->json([
                'message' => 'Payment recorded successfully',
                'data' => $payment->load('payable')
            ], 201);
        });
    }

    /**
     * Payment History for a specific record
     * 
     * Get paginated payment history for a specific Sale or Purchase.
     * @queryParam from_date Filter by start date. Example: 2024-01-01
     * @queryParam to_date Filter by end date. Example: 2024-12-31
     * @queryParam limit Items per page. Example: 10
     * @queryParam page Page number. Example: 1
     */
    public function payableHistory(Request $request, $id, $type)
    {
        $limit = $request->query('limit', 10);
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $payableType = $type === 'sale' ? Sale::class : Purchase::class;
        $payable = $payableType::findOrFail($id);

        $query = $payable->payments();

        if ($fromDate) {
            $query->whereDate('payment_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('payment_date', '<=', $toDate);
        }

        $payments = $query->orderBy('payment_date', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->paginate($limit);

        return response()->json([
            'message' => 'Payment history fetched successfully',
            'data' => $payments->items(),
            'pagination' => [
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
            ]
        ]);
    }

    /**
     * Delete a Payment
     * 
     * Reverts the payment amount from the parent sale/purchase.
     */
    public function destroy($id)
    {
        $payment = Payment::findOrFail($id);
        $payable = $payment->payable;

        // restrict adjusting paid ones?
        // If the user meant "cant adjust if the WHOLE transaction is paid", we already check that in store.
        // But if they mean "once a payment is part of a PAID transaction, don't allow delete", here it is:
        if ($payable->payment_status === 'paid') {
            return response()->json(['message' => 'Cannot adjust or delete payments for a fully paid transaction.'], 422);
        }

        return DB::transaction(function () use ($payment, $payable) {
            $payable->paid_amount -= $payment->amount;
            $payable->due_amount = $payable->grand_total - $payable->paid_amount;

            if ($payable->paid_amount <= 0) {
                $payable->payment_status = 'unpaid';
                $payable->paid_amount = 0;
            } elseif ($payable->paid_amount < $payable->grand_total) {
                $payable->payment_status = 'partial';
            } else {
                $payable->payment_status = 'paid';
            }

            $payable->save();
            $payment->delete();

            return response()->json(['message' => 'Payment deleted and balances reverted successfully']);
        });
    }
}
