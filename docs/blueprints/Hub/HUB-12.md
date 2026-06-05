# PHASE HUB-12: Notification Service

## Tier
Hub

## Component Name
Sovereign Notify

## Description
A unified multi-channel notification engine. It provides a standardized way to send messages to users via Email, In-app notifications, Webhooks, and SMS. It handles template rendering, queuing, and delivery tracking.

## Context7 Research
- **Depends on**: `HUB-04: Identity`, `HUB-10: Queue`, `CORE-12: Compiler`.
- **Channels**: SMTP/API for Email, Database/Redis for In-app, HTTP for Webhooks.
- **Templating**: Uses SuperPHP (`CORE-12`) for rendering email and message templates.

## Architectural Design
- **NotificationManager**: Routes notifications to the appropriate channels.
- **ChannelInterface**: Defines the contract for delivery mechanisms.
- **Notification**: A class defining the content for each channel (e.g., `toMail`, `toDatabase`).
- **WebhookDispatcher**: Specialized channel for outbound system events to external URLs.

### Notification Example
```php
class OrderShipped extends Notification
{
    public function via($notifiable) { return ['mail', 'database']; }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Order Shipped')
            ->view('emails.shipped', ['order' => $this->order]);
    }
}
```

## Interface Contracts

### NotifierInterface
```php
namespace Sovereign\Hub\Contracts;

interface NotifierInterface
{
    /**
     * Send a notification to a specific user or collection of users.
     */
    public function send(mixed $notifiables, object $notification): void;

    /**
     * Send a notification immediately (bypassing the queue).
     */
    public function sendNow(mixed $notifiables, object $notification): void;
}
```

## Integration Strategy
- **Upward**: Depends on `HUB-10` for background delivery and `HUB-04` for user contact details.
- **Downward**: Spoke applications trigger notifications by calling `send()` on the Hub service.
- **UI**: Hub provides a standard SuperPHP component for displaying in-app notification toasts (`s:ui:notifications`).

## CI Verification Criteria
- **Channel Fallback**: If an email fails to send, it must be logged and marked as "failed" without crashing the job worker.
- **Rate Limiting**: Must verify that no more than 10 webhooks are dispatched to a single endpoint per second (referencing HUB-07).
- **Template Rendering**: Must verify that the SuperPHP compiler correctly hydrates email templates with dynamic data.

## SemVer Impact
**Minor**. Standardizes user communication across the stack.
