export interface UsageAttribute {
  name: string;
  type: string;
  optional: boolean;
  choices: string[];
}

export type ExampleAttributes = Record<string, string | null>;

export function usageAttributes(usage: string): UsageAttribute[] {
  const openingTag = findOpeningTag(usage);

  if (!openingTag) return [];

  const attributes: UsageAttribute[] = [];
  const pattern = /(?<![a-z0-9_-])([a-z][a-z0-9_-]*)\s*=\s*\{((?:\\.|[^{}])*)\}/gi;
  let match: RegExpExecArray | null;

  while ((match = pattern.exec(openingTag)) !== null) {
    const parts = match[2].split(';');
    let token = parts.shift() || '';
    let optional = false;

    if (token.endsWith('?')) {
      optional = true;
      token = token.slice(0, -1);
    }

    optional ||= parts.some((option) => option.trim().toLowerCase() === 'optional');

    const separator = token.indexOf('=');
    const tokenName = separator === -1 ? token : token.slice(0, separator);
    const argumentsValue = separator === -1 ? '' : token.slice(separator + 1);
    const type = tokenName.trim().replace(/\d+$/, '').toUpperCase();

    if (!type) continue;

    attributes.push({
      name: match[1].toLowerCase(),
      type,
      optional,
      choices:
        type === 'CHOICE'
          ? argumentsValue
              .split(',')
              .map((choice) => choice.trim())
              .filter(Boolean)
          : [],
    });
  }

  return attributes;
}

export function openingTag(
  tag: string,
  attributes: ExampleAttributes,
): { value: string; emptyValueOffset: number | null } {
  let value = `[${tag}`;
  let emptyValueOffset: number | null = null;

  Object.entries(attributes).forEach(([name, attributeValue]) => {
    if (attributeValue === null) {
      value += ` ${name}=`;
      emptyValueOffset ??= value.length;
      return;
    }

    value += ` ${name}="${escapeAttributeValue(attributeValue)}"`;
  });

  value += ']';

  return { value, emptyValueOffset };
}

function escapeAttributeValue(value: string): string {
  return value.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}

function findOpeningTag(usage: string): string {
  const start = usage.indexOf('[');

  if (start === -1) return '';

  let depth = 0;
  let escaped = false;

  for (let index = start + 1; index < usage.length; index++) {
    const character = usage[index];

    if (escaped) {
      escaped = false;
      continue;
    }

    if (character === '\\') {
      escaped = true;
    } else if (character === '{') {
      depth++;
    } else if (character === '}' && depth > 0) {
      depth--;
    } else if (character === ']' && depth === 0) {
      return usage.slice(start, index + 1);
    }
  }

  return '';
}
