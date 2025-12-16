<?php
/**
 * Payments Controller
 * Handles payment creation and confirmation
 */

require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Rental.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class PaymentsController {
    private $payment;
    private $rental;
    private $logger;
    
    public function __construct() {
        $this->payment = new Payment();
        $this->rental = new Rental();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * POST /api/payments
     * Create a payment for a rental
     */
    public function create(): void {
        $authUser = AuthMiddleware::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $validator = new Validator($data ?? []);
        $validator
            ->required('rental_id', 'Rental')
            ->integer('rental_id', 'Rental ID')
            ->required('payment_type', 'Payment Type')
            ->in('payment_type', ['cash', 'credit_card', 'debit_card', 'gcash', 'maya', 'bank_transfer'], 'Payment Type')
            ->required('amount', 'Amount')
            ->numeric('amount', 'Amount')
            ->min('amount', 1, 'Amount');
        
        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
        }
        
        // Verify rental exists and belongs to user
        $rental = $this->rental->findById($data['rental_id']);
        
        if (!$rental) {
            Response::notFound('Rental not found');
        }
        
        if ($rental['user_id'] != $authUser['user_id'] && $authUser['role'] !== 'admin') {
            Response::forbidden('You can only pay for your own rentals');
        }
        
        if ($rental['status'] === 'cancelled') {
            Response::error('Cannot pay for cancelled rental', 400);
        }
        
        try {
            $paymentId = $this->payment->create([
                'rental_id' => $data['rental_id'],
                'payment_type' => $data['payment_type'],
                'amount' => $data['amount'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null
            ]);
            
            $this->logger->logPayment('create', $paymentId, $data['amount'], $authUser['user_id']);
            
            Response::created([
                'payment_id' => $paymentId,
                'rental_id' => $data['rental_id'],
                'payment_type' => $data['payment_type'],
                'amount' => $data['amount'],
                'is_received' => false,
                'message' => 'Payment recorded. Awaiting confirmation.'
            ], 'Payment created successfully');
            
        } catch (Exception $e) {
            $this->logger->logError('Payment creation failed', $e, $authUser['user_id']);
            Response::serverError('Failed to create payment');
        }
    }
    
    /**
     * PUT /api/payments/{id}/confirm
     * Confirm payment as received (admin only)
     */
    public function confirm(int $id): void {
        $authUser = AuthMiddleware::requireAdmin();
        
        $payment = $this->payment->findById($id);
        
        if (!$payment) {
            Response::notFound('Payment not found');
        }
        
        if ($payment['is_received']) {
            Response::error('Payment already confirmed', 409);
        }
        
        $this->payment->confirmReceived($id, $authUser['user_id']);
        
        // Check if rental is fully paid
        $rental = $this->rental->findById($payment['rental_id']);
        $totalReceived = $this->payment->getTotalReceived($payment['rental_id']);
        
        // Get current total (including any overtime)
        $totalDue = $rental['current_total'] ?? $rental['total_price'];
        
        $isFullyPaid = $totalReceived >= $totalDue;
        
        // If fully paid and pending, confirm the rental
        if ($isFullyPaid && $rental['status'] === 'pending') {
            $this->rental->confirm($payment['rental_id']);
        }
        
        $this->logger->logPayment('confirm', $id, $payment['amount'], $authUser['user_id']);
        $this->logger->logAdmin('confirm_payment', $authUser['user_id'], [
            'payment_id' => $id,
            'amount' => $payment['amount']
        ]);
        
        Response::success([
            'payment_id' => $id,
            'is_received' => true,
            'total_paid' => $totalReceived,
            'total_due' => $totalDue,
            'is_fully_paid' => $isFullyPaid,
            'rental_status' => $isFullyPaid && $rental['status'] === 'pending' ? 'confirmed' : $rental['status']
        ], 'Payment confirmed');
    }
    
    /**
     * GET /api/payments/rental/{rental_id}
     * Get payments for a rental
     */
    public function getByRental(int $rentalId): void {
        $authUser = AuthMiddleware::requireAuth();
        
        $rental = $this->rental->findById($rentalId);
        
        if (!$rental) {
            Response::notFound('Rental not found');
        }
        
        // Check ownership or admin
        if ($rental['user_id'] != $authUser['user_id'] && $authUser['role'] !== 'admin') {
            Response::forbidden('You can only view payments for your own rentals');
        }
        
        $payments = $this->payment->getByRental($rentalId);
        $totalPaid = $this->payment->getTotalReceived($rentalId);
        $totalDue = $rental['current_total'] ?? $rental['total_price'];
        
        $this->logger->logApiRequest('GET', "/api/payments/rental/$rentalId", $authUser['user_id']);
        
        Response::success([
            'payments' => $payments,
            'total_paid' => $totalPaid,
            'total_due' => $totalDue,
            'balance' => $totalDue - $totalPaid
        ]);
    }
    
    /**
     * GET /api/payments/my-payments
     * Get current user's payments
     */
    public function myPayments(): void {
        $authUser = AuthMiddleware::requireAuth();
        
        // Get user's rentals first
        $rentals = $this->rental->getByUser($authUser['user_id']);
        
        $allPayments = [];
        foreach ($rentals as $rental) {
            $payments = $this->payment->getByRental($rental['id']);
            foreach ($payments as $payment) {
                $payment['car_info'] = $rental['make'] . ' ' . $rental['model'];
                $allPayments[] = $payment;
            }
        }
        
        $this->logger->logApiRequest('GET', '/api/payments/my-payments', $authUser['user_id']);
        
        Response::success($allPayments);
    }
}
