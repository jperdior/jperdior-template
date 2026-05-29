import nextCoreWebVitals from 'eslint-config-next/core-web-vitals';

export default [
  ...nextCoreWebVitals,
  { ignores: ['node_modules/**', '.next/**', 'out/**'] },
];
