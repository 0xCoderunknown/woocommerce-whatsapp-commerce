@echo off
setlocal

set "NODE_BIN=node"
where node >nul 2>&1
if %ERRORLEVEL% equ 0 goto RUN_TESTS

if exist "%LOCALAPPDATA%\ms-playwright-go\1.57.0\node.exe" (
    set "NODE_BIN=%LOCALAPPDATA%\ms-playwright-go\1.57.0\node.exe"
    goto RUN_TESTS
)

if exist "C:\Program Files\nodejs\node.exe" (
    set "NODE_BIN=C:\Program Files\nodejs\node.exe"
    goto RUN_TESTS
)

echo [ERROR] Node.js binary could not be found.
exit /b 1

:RUN_TESTS
echo --- Running 0-Dependency Unit Tests ---
"%NODE_BIN%" --test tests/unit/*.test.js
if %ERRORLEVEL% neq 0 (
    echo [FAIL] Unit tests failed!
    exit /b %ERRORLEVEL%
)

echo.
echo --- Running PHP Domain, Invariant and Lint Verification ---
"%NODE_BIN%" tests/php/runner.js
if %ERRORLEVEL% neq 0 (
    echo [FAIL] PHP verification failed!
    exit /b %ERRORLEVEL%
)

echo.
echo [PASS] All test suites passed successfully!
exit /b 0
