<?php

declare(strict_types=1);

/*
 * The WordPress REST classes and functions the REST controllers use, for
 * tests that call a controller method directly. Load it only from
 * @runInSeparateProcess tests: it declares global classes / functions that
 * must not leak into the rest of the suite.
 *
 * The current user is $GLOBALS['stageart_test_wp']['current_user'] (0 when
 * unset). A WP_REST_Request holds the merged parameters - URL, query and
 * JSON body - as WordPress's get_param() sees them: a JSON number stays an
 * int or a float, a query or form value is a string.
 */

if (! class_exists('WP_REST_Request', false)) {
    class WP_REST_Request
    {
        /** @var array<string, mixed> */
        private array $params;

        /**
         * @param array<string, mixed> $params
         */
        public function __construct(array $params = [])
        {
            $this->params = $params;
        }

        /**
         * @return mixed
         */
        public function get_param($key)
        {
            return $this->params[$key] ?? null;
        }
    }
}

if (! class_exists('WP_REST_Response', false)) {
    class WP_REST_Response
    {
        /** @var mixed */
        private $data;
        private int $status;

        /**
         * @param mixed $data
         */
        public function __construct($data = null, $status = 200)
        {
            $this->data = $data;
            $this->status = (int) $status;
        }

        /**
         * @return mixed
         */
        public function get_data()
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        /** @var array<string, string> */
        private array $headers = [];

        public function header($key, $value, $replace = true): void
        {
            $this->headers[(string) $key] = (string) $value;
        }

        /**
         * @return array<string, string>
         */
        public function get_headers(): array
        {
            return $this->headers;
        }
    }
}

if (! class_exists('WP_Error', false)) {
    class WP_Error
    {
        private string $code;
        private string $message;
        /** @var mixed */
        private $data;

        /**
         * @param mixed $data
         */
        public function __construct($code = '', $message = '', $data = '')
        {
            $this->code = (string) $code;
            $this->message = (string) $message;
            $this->data = $data;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        /**
         * @return mixed
         */
        public function get_error_data()
        {
            return $this->data;
        }
    }
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id()
    {
        return (int) ($GLOBALS['stageart_test_wp']['current_user'] ?? 0);
    }
}
