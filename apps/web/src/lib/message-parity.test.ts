import { describe, it, expect } from 'vitest';
import en from '../../messages/en.json';
import es from '../../messages/es.json';

function keyPaths(obj: Record<string, unknown>, prefix = ''): string[] {
  return Object.entries(obj).flatMap(([key, value]) => {
    const path = prefix ? `${prefix}.${key}` : key;
    return value !== null && typeof value === 'object'
      ? keyPaths(value as Record<string, unknown>, path)
      : [path];
  });
}

describe('message catalog parity', () => {
  it('es.json has exactly the same key set as en.json', () => {
    const enKeys = keyPaths(en).sort();
    const esKeys = keyPaths(es).sort();

    // Symmetric diff surfaces both missing and extra keys in one assertion.
    const missingInEs = enKeys.filter((k) => !esKeys.includes(k));
    const extraInEs = esKeys.filter((k) => !enKeys.includes(k));

    expect({ missingInEs, extraInEs }).toEqual({ missingInEs: [], extraInEs: [] });
  });
});
