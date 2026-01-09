<?php

namespace DvTeam\ChatGPT\Common;

interface MessageInterceptorInterface {
	/**
	 * @param ChatEnquiry $enquiry
	 * @param callable(ChatEnquiry): string $next
	 * @return string
	 */
	public function invoke(ChatEnquiry $enquiry, callable $next): string;
}
