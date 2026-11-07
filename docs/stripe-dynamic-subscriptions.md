# Stripe Dynamic Subscriptions

Esta integracion cobra membresias usando el plan guardado en la base de datos de Zentra.

## Variables necesarias

```env
APP_URL=http://localhost/Saas
STRIPE_SECRET_KEY=sk_test_xxxxx
STRIPE_PUBLISHABLE_KEY=pk_test_xxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxx
```

Los `STRIPE_PRICE_*` quedaron solo como compatibilidad opcional con precios fijos antiguos.

## Flujo actual

1. El frontend envia solo `plan_id`.
2. El backend busca el plan en `planes`.
3. Si es `Free`, activa la cuenta sin llamar a Stripe.
4. Si es `Enterprise`, devuelve contacto con ventas.
5. Si es pagado, crea `Checkout Session` en `mode=subscription` con `price_data.recurring` dinamico.
6. `payment_success.php` solo informa estado.
7. `stripe_webhook.php` confirma el pago y activa la cuenta.

## Endpoints

- `src/payments/create_checkout_session.php`
- `src/payments/payment_success.php`
- `src/payments/payment_cancel.php`
- `src/payments/stripe_webhook.php`

Las rutas antiguas siguen existiendo como wrappers de compatibilidad:

- `src/pages/samples/payment_success.php`
- `src/pages/samples/payment_cancel.php`
- `src/api/stripe/webhook.php`

## Probar en local

1. Levanta el proyecto en `http://localhost/Saas`.
2. Inicia sesion en Stripe CLI:

```bash
stripe login
```

3. Escucha webhooks hacia el endpoint local:

```bash
stripe listen --forward-to http://localhost/Saas/src/payments/stripe_webhook.php
```

4. Copia el `whsec_...` que te devuelve Stripe CLI al archivo `.env` en `STRIPE_WEBHOOK_SECRET`.
5. Usa una tarjeta de prueba como `4242 4242 4242 4242`, fecha futura y cualquier CVC.

Si quieres limitar eventos durante pruebas, puedes usar:

```bash
stripe listen --events checkout.session.completed,invoice.paid,checkout.session.expired,customer.subscription.updated,customer.subscription.deleted --forward-to http://localhost/Saas/src/payments/stripe_webhook.php
```

## Validaciones manuales recomendadas

- Registro con `Free`
- Registro con `Light`, `Pro` y `Ultra`
- Cancelacion de pago
- Webhook duplicado
- Plan inactivo
- Registro con Google y seleccion de plan
- Revisar tablas `subscriptions`, `payments`, `usuarios` y `registros_pago_pendientes`
- Revisar `email_logs` si SMTP falla o si quieres confirmar que no hubo duplicados
