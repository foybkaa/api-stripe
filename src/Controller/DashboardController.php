<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DashboardController extends AbstractController
{
    private StripeClient $gateway;
    private EntityManagerInterface $manager;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->gateway = new StripeClient($_ENV['STRIPE_SECRET_KEY']);
        $this->manager = $manager;
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'controller_name' => 'DashboardController',
        ]);
    }

    #[Route('/checkout', name: 'app_checkout', methods: ["POST"])]
    public function checkout(Request $request): Response
    {
        $amount = $request->request->get('amount');
        $mode = $request->request->get('mode');
        $quantity = $request->request->get('quantity');
        $productName = $mode === 'subscription' ? 'Abonnement' : 'Ressources paiement unique';

        $priceData = [
            'currency' => $_ENV['STRIPE_CURRENCY'],
            'product_data' => [
                'name' => $productName,
            ],
            'unit_amount' => intval($amount),
        ];

        if ($mode === 'subscription') {
            $priceData['recurring'] = [
                'interval' => 'month',
            ];
        }

        $checkout = $this->gateway->checkout->sessions->create([
            'line_items' => [[
                'price_data' => $priceData,
                'quantity' => $quantity,
            ]],
            'mode' => $mode,
            'success_url' => 'https://localhost/success?id_sessions={CHECKOUT_SESSION_ID}',
            'cancel_url' => 'https://localhost/cancel?id_sessions={CHECKOUT_SESSION_ID}',
            'locale' => 'fr',
        ]);

        return $this->redirect($checkout->url);
    }


    #[Route('/success', name: 'app_success')]
    public function success(Request $request): Response
    {
        $id_sessions=$request->query->get('id_sessions');

        $customer=$this->gateway->checkout->sessions->retrieve(
            $id_sessions,
            []
        );

        $mode = $customer['mode'];
        $paymentStatus = $customer['payment_status'];

        if ($paymentStatus === 'paid') {
            $user = $this->getUser();

            if ($mode === 'payment') {
                $user->setHasAccessRessource(true);
                $user->setRoles(["ROLE_CUSTOMER_1"]);
            } elseif ($mode === 'subscription') {
                $user->setSubscriptionStatus('active');
                $user->setRoles(["ROLE_CUSTOMER_2"]);
            }

            $this->manager->persist($user);
            $this->manager->flush();

            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->tokenStorage->setToken($token);
        }

        return $this->render('stripe/success.html.twig',[
            'mode' => $mode
        ]);
    }

    #[Route('/cancel', name: 'app_cancel')]
    public function cancel(): Response
    {
        return $this->render('stripe/cancel.html.twig');
    }

    #[Route('/ressources', name: 'app_ressources')]
    public function ressources(): Response
    {
        $user = $this->getUser();

        if (!$user || !$user->hasAccessRessource()) {
            throw new AccessDeniedException('Vous n\'avez pas accès à cette ressource.');
        }

        return $this->render('dashboard/ressources.html.twig');

    }

    #[Route('/subscription', name: 'app_subscription')]
    public function subscription(): Response
    {
        $user = $this->getUser();

        if (!$user || $user->getSubscriptionStatus() !== 'active') {
            throw new AccessDeniedException('Vous n\'avez pas accès à cette ressource.');
        }

        return $this->render('dashboard/subscription.html.twig');
    }
}
