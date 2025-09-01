# Psalm Internal Trait Enforcer

Enforces internal visibility boundaries for trait usage in Psalm.

## Configuration

Add the plugin to your `psalm.xml` and set an optional `policy` inside the `<pluginClass>` configuration. Supported values:
- namespace (default): consumer namespace must equal, be a child of, or a parent of the internal namespace.
- package: only the top-level vendor segment must match (e.g., `Vendor\...`).

Example psalm.xml snippet:

```xml
<plugins>
  <pluginClass class="Ubean\Psalm\Internal\TraitEnforcer\Plugin">
    <policy>namespace</policy>
  </pluginClass>
</plugins>
```

If not specified, the policy defaults to `namespace`.

## Issue name
This plugin emits the issue `InternalTraitUse` when a class uses a trait outside the allowed internal boundary.
