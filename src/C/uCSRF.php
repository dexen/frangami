<?php

class uCSRF
{
	private $validity_seconds;
	private $session_id;
	private $field_in_session;
	private $secret_material;

	function __construct(string $field_in_session = 'uCSRF', string $session_id = null, int $validity_seconds = 3600)
	{
		if ($session_id === null)
			$session_id = session_id();
		if (empty($session_id))
			throw new Exception(sprintf('expected a session id, got empty'));
		if ($validity_seconds <= 0)
			throw new \Exception(sprintf('expected validity_seconds > 0, got "%s"', $validity_seconds));

		$this->field_in_session = $field_in_session;
		$this->validity_seconds = $validity_seconds;
		$this->session_id = $session_id;

		if (isset($_SESSION[$this->field_in_session])) {
			if ($_SESSION[$this->field_in_session] instanceof self)
				$_SESSION[$this->field_in_session]->expectHasSecretMaterial();
			else
				throw new \LogicException('expected intance of uCSRF in session'); }
		else {
			$this->setUpSecretMaterial();
			$_SESSION[$this->field_in_session] = $this; }
	}

	private
	function message(string $timestamp) : string
	{
		return $timestamp .':' .$this->session_id;
	}

	private
	function hashMessage(string $message) : string
	{
		return sodium_crypto_generichash($message, $this->secret_material);
	}

	function getCsrfToken() : string
	{
		$timestamp = time();
		return base64_encode($timestamp .':' .$this->hashMessage($this->message($timestamp)));
	}

	private
	function expectUserTimestampSatisfiesTimeout(int $user_timestamp)
	{
		if (empty($user_timestamp))
			throw new \RuntimeException('unexpected empty timestamp');
		if ($user_timestamp < 0)
			throw new \RuntimeException(sprintf('unexpected timestamp: "%s"', $user_timestamp));

		$current_timestamp = time();
		if (empty($current_timestamp))
			throw new \LogicException('unexpected empty timestamp');
		if ($current_timestamp < 0)
			throw new \LogicException(sprintf('unexpected timestamp: "%s"', $current_timestamp));

		if ($user_timestamp < ($current_timestamp - $this->validity_seconds))
			throw new \RuntimeException(sprintf('token too old: "%s"', $user_timestamp));
		if ($user_timestamp > $current_timestamp)
			throw new \RuntimeException(sprintf('token too far into the future: "%s"', $user_timestamp));
	}

	function expectUserSuppliedTokenMatchesHash(string $user_supplied_timestamp, string $user_supplied_message_hash)
	{

		$expected_message_hash = $this->hashMessage($this->message($user_supplied_timestamp));
### FIXME - compare the timestamp to the timestamp limit
		if (sodium_memcmp($expected_message_hash, $user_supplied_message_hash) !== 0)
			throw new \RuntimeException('CSRF token does not match expected value');
	}

	function expectCsrfTokenMatch(string $token)
	{
		$raw_token = base64_decode($token, $strict = true);
		if ($raw_token === false)
			throw new \RuntimeException('CSRF token malformed');
		if (strpos($raw_token, ':') === false)
			throw new \RuntimeException('CSRF token malformed');
		[$user_supplied_timestamp, $user_supplied_message_hash] = explode(':', $raw_token, 2);
		$this->expectUserTimestampSatisfiesTimeout($user_supplied_timestamp);
		$this->expectUserSuppliedTokenMatchesHash($user_supplied_timestamp, $user_supplied_message_hash);
	}

	static
	function token() : string { return (new static)->getCsrfToken(); }

	private
	function setUpSecretMaterial() { $this->secret_material = sodium_crypto_generichash_keygen(); }

	private
	function expectHasSecretMaterial()
	{
		if (empty($this->secret_material))
			throw new Exception('expected secret material');
	}

	static
	function expectTokenMatch(string $token) { return (new static)->expectCsrfTokenMatch($token); }

	private static
	function fieldInSession() { return (new static)->field_in_session; }

	static
	function expectPostTokenMatch() { static::expectTokenMatch($_POST[static::fieldInSession()] ?? ''); }

	static
	function htmlHiddenInput(string $name = 'uCSRF') : string
	{
		return '<input type="hidden" name="' .H($name) .'" value="' .H(static::token()) .'">';
	}
}
