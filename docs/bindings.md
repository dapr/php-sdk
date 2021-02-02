# Bindings Overview

## Introduction

Using bindings, you can trigger your app with events coming in from external systems, or interface with external
systems.

### Configuring the binding

You can find available and supported
binding [in the Dapr docs](https://v1-rc3.docs.dapr.io/operations/components/setup-bindings/supported-bindings/). Once
you settle on a name for your component and get it configured, you just need to create a route for the binding and
handle its inputs and/or use the `DaprClient` to use an output binding. 
