<?php

namespace App\Jobs;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMessage;
use App\Models\PushSubscription;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlab\WebPush\Subscription;
use Minishlab\WebPush\WebPush;

/**
 * نوتیفیکیشن پیام جدید گروه برای اعضایی که همین الان توی چت اون گروه نیستن.
 * با dispatchAfterResponse صدا زده می‌شه، پس ارسال پیام رو کند نمی‌کنه.
 */
class SendGroupMessagePush
{
    use Dispatchable;

    public function __construct(
        private int $messageId,
        private array $mentionedIds = [],
        private ?int $repliedToSenderId = null,
    ) {}

    public function handle(): void
    {
        if (!config('webpush.public_key') || !config('webpush.private_key')) {
            return; // VAPID تنظیم نشده
        }

        $message = GroupMessage::with(['sender', 'attachments'])->find($this->messageId);
        $group = $message ? Group::find($message->group_id) : null;
        if (!$message || !$group) {
            return;
        }

        $recipientIds = GroupMember::where('group_id', $group->id)
            ->where('user_id', '!=', $message->sender_id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // اونایی که همین الان توی چت این گروه هستن نوتیف نمی‌گیرن
        $recipientIds = array_values(array_diff($recipientIds, $this->idsInChat($group->id)));
        if (!$recipientIds) {
            return;
        }

        $subscriptions = PushSubscription::whereIn('user_id', $recipientIds)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('webpush.subject'),
                'publicKey' => config('webpush.public_key'),
                'privateKey' => config('webpush.private_key'),
            ],
        ], ['TTL' => 86400], 10);

        foreach ($subscriptions as $sub) {
            $payload = json_encode([
                'title' => $group->name,
                'body' => $this->bodyFor(
                    $message,
                    in_array((int) $sub->user_id, $this->mentionedIds, true),
                    $this->repliedToSenderId !== null && (int) $sub->user_id === $this->repliedToSenderId,
                ),
                'tag' => 'group-' . $group->id,
                'groupId' => $group->id,
                'url' => '/workPlan?group=' . $group->id,
            ], JSON_UNESCAPED_UNICODE);

            $webPush->queueNotification(Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->p256dh_key,
                'authToken' => $sub->auth_token,
                'contentEncoding' => $sub->content_encoding,
            ]), $payload);
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }
            if ($report->isSubscriptionExpired()) {
                // اشتراک منقضی/لغوشده (۴۰۴ یا ۴۱۰) پاک می‌شه
                PushSubscription::where('endpoint_hash', sha1($report->getEndpoint()))->delete();
            } else {
                Log::warning('webpush failed', ['reason' => $report->getReason()]);
            }
        }
    }

    /** آیدی اعضایی که الان کانال presence این گروه رو باز دارن (توی چت گروهن). */
    private function idsInChat(int $groupId): array
    {
        try {
            $pusher = Broadcast::connection('pusher')->getPusher();
            $res = $pusher->getPresenceUsers('presence-online-group.' . $groupId);
            $users = is_array($res) ? ($res['users'] ?? []) : ($res->users ?? []);

            return array_map(fn ($u) => (int) (is_array($u) ? $u['id'] : $u->id), $users);
        } catch (\Throwable $e) {
            // اگه Pusher جواب نداد، برای همه می‌فرستیم تا نوتیفی از دست نره
            Log::warning('webpush presence lookup failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function bodyFor(GroupMessage $m, bool $mentioned, bool $replied): string
    {
        $text = trim((string) $m->text);

        if ($text === '') {
            $text = match ($m->attachments->first()?->type) {
                'image' => '📷 عکس',
                'voice' => '🎤 پیام صوتی',
                'file' => '📎 فایل',
                default => $m->task_id ? '📋 تسک' : 'پیام جدید',
            };
        }

        $text = Str::limit(preg_replace('/\s+/u', ' ', $text), 120);
        $prefix = $mentioned ? 'شما رو منشن کرد: ' : ($replied ? 'به پیام شما جواب داد: ' : '');

        return ($m->sender?->name ?? 'یک نفر') . ': ' . $prefix . $text;
    }
}
