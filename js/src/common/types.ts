import type { ExampleAttributes } from './bbcodeUsage';

export interface ToolbarRule {
  id: string;
  tag: string;
  name: string;
  icon: string;
  buttonLabel: string;
  buttonLabelTranslationKey: string | null;
  example: string;
  exampleAttributes: ExampleAttributes;
  sortOrder: number;
}

export type RuleType = 'bbcode' | 'media';
export type MediaSourceRuleType = 'extract' | 'redirect';

export interface MediaSourceRule {
  type: MediaSourceRuleType;
  pattern: string;
}

export type MediaTestFailureReason =
  | 'empty_url'
  | 'url_too_long'
  | 'missing_embed_capture'
  | 'invalid_embed_url'
  | 'invalid_pattern'
  | 'missing_pattern_capture'
  | 'missing_extract_rule'
  | 'empty_capture'
  | 'redirect_failed'
  | 'no_match'
  | 'request_failed';

export interface MediaTestResult {
  matched: boolean;
  reason?: MediaTestFailureReason;
  matchType?: MediaSourceRuleType;
  ruleNumber?: number;
  captures?: Record<string, string>;
  embedUrl?: string;
}

export interface RuleFormData {
  ruleType: RuleType;
  name: string;
  tag: string;
  description: string;
  usage: string;
  template: string;
  cssDeclarations: string;
  icon: string;
  buttonLabel: string;
  example: string;
  exampleAttributes: ExampleAttributes;
  enabled: boolean;
  toolbarEnabled: boolean;
  extractPattern: string;
  sourceRules: MediaSourceRule[];
  captureDefaults: Record<string, string>;
  embedUrl: string;
  iframeAttributes: string;
  aspectRatio: string;
  sortOrder: number;
}

export interface RuleAttributes extends RuleFormData {
  builtIn: boolean;
  defaultAttributes: RuleFormData | null;
  createdAt?: string | null;
  updatedAt?: string | null;
}

export interface RuleResource {
  type: 'bbcode-studio-rules';
  id: string;
  attributes: RuleAttributes;
}
