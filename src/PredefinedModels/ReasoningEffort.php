<?php

namespace DvTeam\ChatGPT\PredefinedModels;

enum ReasoningEffort: string {
	case None = 'none';
	case Low = 'low';
	case Medium = 'medium';
	case High = 'high';
	case XHigh = 'xhigh';
	case Max = 'max';
}
