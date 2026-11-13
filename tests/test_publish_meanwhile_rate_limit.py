"""Bounded, polite retry tests for the SSH/WordPress publisher."""
import unittest
from unittest.mock import patch

from scripts import publish_meanwhile as publisher


class PublisherBackoffTests(unittest.TestCase):
    def test_first_response_json_never_sleeps(self):
        with patch.object(publisher, "run_command", return_value='{"status":"publish"}') as run, \
             patch.object(publisher.time, "sleep") as sleep:
            output = publisher.run_publisher_with_backoff(None, "php import.php", "film-post")
        self.assertEqual(output, '{"status":"publish"}')
        self.assertEqual(run.call_count, 1)
        sleep.assert_not_called()

    def test_bootstrap_rate_limit_retries_after_cooldown(self):
        def output(client, command, stderr_sink=None):
            if stderr_sink is not None:
                stderr_sink.append("Meanwhile bootstrapping WordPress")
            output.calls += 1
            return "Rate limit exceeded. Please retry later." if output.calls == 1 else '{"status":"publish"}'
        output.calls = 0
        with patch.object(publisher, "run_command", side_effect=output) as run, \
             patch.object(publisher.time, "sleep") as sleep:
            result = publisher.run_publisher_with_backoff(None, "php import.php", "film-post")
        self.assertEqual(result, '{"status":"publish"}')
        self.assertEqual(run.call_count, 2)
        sleep.assert_called_once_with(60)

    def test_rate_limit_stops_after_bounded_retries(self):
        with patch.object(publisher, "run_command", return_value="Rate limit exceeded. Please retry later.") as run, \
             patch.object(publisher.time, "sleep") as sleep:
            with self.assertRaisesRegex(RuntimeError, "No further automatic retries"):
                publisher.run_publisher_with_backoff(None, "php import.php", "film-post")
        self.assertEqual(run.call_count, 4)
        self.assertEqual([call.args[0] for call in sleep.call_args_list], [60, 180, 300])

    def test_unexpected_non_json_is_not_automatically_retried(self):
        with patch.object(publisher, "run_command", return_value="Unexpected PHP output") as run, \
             patch.object(publisher.time, "sleep") as sleep:
            result = publisher.run_publisher_with_backoff(None, "php import.php", "film-post")
        self.assertEqual(result, "Unexpected PHP output")
        self.assertEqual(run.call_count, 1)
        sleep.assert_not_called()


if __name__ == "__main__":
    unittest.main()
