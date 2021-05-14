test:
	./run-tests.sh

cstest:
	composer cs

csfix:
	composer csfix

psalm:
	composer psalm

stan:
	composer stan

get-security:
	composer get-security

security:
	composer security

.PHONY: cs
cs: csfix cstest

.PHONY: static
static: psalm stan

.PHONY: all
all: static test cs security
