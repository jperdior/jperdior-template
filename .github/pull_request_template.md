## What

<!-- One sentence: what does this PR do? -->

## Why

<!-- The motivation. Link to the spec or issue if one exists. -->

## How

<!-- Key implementation decisions. Skip the obvious. -->

## Test plan

- [ ] `make lint` exits 0
- [ ] `make test` exits 0
- [ ] Manually tested: <!-- describe the happy path you exercised -->

## Checklist

- [ ] No cross-bounded-context Domain imports (`deptrac` will catch them in CI)
- [ ] Domain entities stay pure PHP; persistence via `#[ORM\*]` attributes on `*Model` classes (no ORM attributes on domain entities)
- [ ] New migration reviewed — `up()` and `down()` both correct
- [ ] No credentials or tokens committed
- [ ] OpenAPI-affecting change → `make gen-api` run and diff committed
