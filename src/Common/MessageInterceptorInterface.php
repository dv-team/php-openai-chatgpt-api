<?php

namespace DvTeam\ChatGPT\Common;

interface MessageInterceptorInterface {
	/**
	 * @param ChatEnquiry $enquiry
	 * @param callable(ChatEnquiry $enquiry): string $fn
	 * @return string
	 */
	public function invoke(ChatEnquiry $enquiry, $fn): string;
}