<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Admin Tools</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="title" content="Admin Tools">
     <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css"
          integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q=" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css"
          integrity="sha256-dSokZseQNT08wYEWiz5iLI8QPlKxG+TswNRD8k35cpg=" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ assetLink('css','BootstrapIcons') }}">
    <link rel="stylesheet" href="{{ assetLink('css','AdminLTE') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.css"
          integrity="sha256-4MX+61mt9NVvvuPjUWdUdyfZfxSB1/Rf9WtqRHgG5S0=" crossorigin="anonymous">

    @vite(['resources/js/app.js','resources/css/app.css'])
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div id="app">
    <AppRender>
    </AppRender>
</div>
<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/browser/overlayscrollbars.browser.es6.min.js"
        integrity="sha256-H2VM7BKda+v2Z4+DRy69uknwxjyDRhszjXFhsL4gD3w=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha256-whL0tQWoY1Ku1iskqPFvmZ+CHsvmRWx/PIoEvIeWh4I=" crossorigin="anonymous"></script>
<script src="{{ assetLink('js','Bootstrap') }}"></script>
<script src="{{ assetLink('js','AdminLTE') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js"
        integrity="sha256-+vh8GkaU7C9/wbSLIcwq82tQ2wTf44aOHA8HlBMwRI8=" crossorigin="anonymous"></script>
</body>

</html>
