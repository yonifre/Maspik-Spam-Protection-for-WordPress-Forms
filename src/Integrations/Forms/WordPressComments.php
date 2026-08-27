<?php

declare(strict_types=1);

namespace Maspik\Integrations\Forms;

use Maspik\Application\SpamGate;
use Maspik\Domain\Model\Field;
use Maspik\Domain\Model\FieldType;
use Maspik\Infrastructure\Matrix\DirectPostSignal;
use Maspik\Integrations\AbstractFormIntegration;

/**
 * WordPress core comments.
 * Hook: preprocess_comment ($commentdata). Reject: wp_die() (WP's own spam path).
 * Always available — no plugin required.
 */
final class WordPressComments extends AbstractFormIntegration
{
    public function id(): string
    {
        return 'comments';
    }

    public function label(): string
    {
        return 'Wordpress Comments';
    }

    public function toggleKey(): string
    {
        return 'maspik_support_wp_comment';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function register(SpamGate $gate): void
    {
        // Direct-POST evidence for InputGate. wp-comments-post.php always posts
        // comment_post_ID; without it the request did not come from a rendered
        // comment form. Signal only - it never blocks on its own. Score 5
        // identifies WordPress comments as the source (v2: wp-general.php).
        add_filter('maspik/direct_post_score', static function ($score, $submission = null) {
            return self::directPostSentinel($submission) === null
                ? $score
                : max((int) $score, DirectPostSignal::WP_COMMENTS);
        }, 10, 2);

        // The matching sentinel, forwarded as `maspik_referrer` (v2: wp-general.php).
        add_filter('maspik/direct_post_referrer', static function ($sentinel, $submission = null) {
            return self::directPostSentinel($submission) ?? $sentinel;
        }, 10, 2);

        add_filter('preprocess_comment', function ($commentdata) use ($gate) {
            $postId = isset($commentdata['comment_post_ID']) ? (int) $commentdata['comment_post_ID'] : 0;
            if (apply_filters('maspik_disable_wp_comments_spam_check', false, $postId, $commentdata)) {
                return $commentdata;
            }

            // Someone who can moderate comments is not a spammer, and the
            // request they are making did not come from a rendered page.
            //
            // preprocess_comment fires for every comment WordPress creates,
            // including a reply typed on the Comments screen and anything a
            // plugin or WP-CLI inserts. None of those carry the guard fields:
            // the script that writes the verification key is enqueued on
            // wp_enqueue_scripts, which never runs in wp-admin. The submission
            // therefore arrived with an empty key and was blocked — the site
            // owner answering their own visitor, told their reply looked like
            // spam, with no way to see why.
            if (self::isTrustedModerator()) {
                return $commentdata;
            }

            $fields = self::fieldsFrom($commentdata);
            $verdict = $gate->evaluate($this->submission($fields));
            if ($verdict->isSpam) {
                wp_die(
                    esc_html($gate->errorMessage($verdict)),
                    esc_html__('Comment blocked', 'contact-forms-anti-spam'),
                    ['response' => 403, 'back_link' => true]
                );
            }

            return $commentdata;
        }, 10, 1);
    }

    /**
     * A logged-in user allowed to moderate comments on this site.
     *
     * Deliberately `moderate_comments` rather than "is logged in": a subscriber
     * commenting on the front end is an ordinary visitor whose comment should
     * still be checked, and on a multi-author site an editor replying from the
     * dashboard should not be. The capability is exactly the permission
     * WordPress itself uses to decide who may approve a comment.
     */
    private static function isTrustedModerator(): bool
    {
        return function_exists('current_user_can') && is_user_logged_in() && current_user_can('moderate_comments');
    }

    /**
     * @param array<string, mixed> $data
     * @return Field[]
     */
    public static function fieldsFrom(array $data): array
    {
        $map = [
            'comment_author' => FieldType::TEXT,
            'comment_author_email' => FieldType::EMAIL,
            'comment_author_url' => FieldType::URL,
            'comment_content' => FieldType::TEXTAREA,
        ];
        $fields = [];
        foreach ($map as $key => $type) {
            if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
                $fields[] = new Field($key, $type, $data[$key]);
            }
        }

        return $fields;
    }

    /**
     * Which field the real comment form always posts is missing, or null when
     * the request looks genuine. Same three markers v2 reported, in the same
     * order, so the sentinels keep their server-side meaning.
     *
     * @param mixed $submission
     */
    private static function directPostSentinel($submission): ?string
    {
        if ($submission === null || $submission->source !== 'comments') {
            return null;
        }

        $postId = isset($_POST['comment_post_ID'])
            ? trim((string) wp_unslash($_POST['comment_post_ID'])) : '';
        if ($postId === '') {
            return 'no_comment_post_id';
        }

        if (! isset($_POST['comment_parent'])) {
            return 'no_comment_parent';
        }

        $body = isset($_POST['comment']) ? trim((string) wp_unslash($_POST['comment'])) : '';
        if ($body === '') {
            return 'no_comment_body';
        }

        return null;
    }
}
