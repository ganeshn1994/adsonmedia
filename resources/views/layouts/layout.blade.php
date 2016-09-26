<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
<meta charset="utf-8"/>
<title>Pages - Front End</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
<link rel="apple-touch-icon" href="pages/ico/60.png">
<link rel="apple-touch-icon" sizes="76x76" href="pages/ico/76.png">
<link rel="apple-touch-icon" sizes="120x120" href="pages/ico/120.png">
<link rel="apple-touch-icon" sizes="152x152" href="pages/ico/152.png">
<link rel="icon" type="image/x-icon" href="favicon.ico"/>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta content="" name="description"/>
<meta content="" name="author"/>
 
<link href="/assets/css/pace-theme-flash.css" rel="stylesheet" type="text/css"/>
<link href="/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="/assets/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="/assets/css/swiper.css" rel="stylesheet" type="text/css" media="screen"/>
 
 
<link class="main-stylesheet" href="/assets/css/pages.css" rel="stylesheet" type="text/css"/>
<link href="/assets/css/pages-icons.css" rel="stylesheet" type="text/css"/>
</head>
<body>
 
<nav class="header md-header light-solid " data-pages="header">
<div class="container relative">
<div class="pull-left">
<div class="header-inner">
<img src="/assets/img/Logomakr_62Fr5N.png" width="152" height="21" data-src-retina="/assets/img/Logomakr_62Fr5N.png" class="logo" alt="logo">
</div>
</div>
 
<div class="pull-right">
<div class="header-inner">
<a href="#" class="search-toggle visible-sm-inline visible-xs-inline p-r-10"><i class="fs-14 pg-search"></i></a>
<div class="visible-sm-inline visible-xs-inline menu-toggler pull-right p-l-10" data-pages="header-toggle" data-pages-element="#header">
<div class="one"></div>
<div class="two"></div>
<div class="three"></div>
</div>
</div>
</div>
<div class="pull-right menu-content clearfix" data-pages-direction="slideRight" id="header">
 
<div class="pull-right">
<a href="#" class="text-black link padding-10 visible-xs-inline visible-sm-inline pull-right m-t-10 m-b-10 m-r-10" data-pages="header-toggle" data-pages-element="#header">
<i class=" pg-close_line"></i>
</a>
</div>
<div class="p-t-10 p-b-10 clearfix m-b-5 hidden-xs hidden-sm">
<div class="pull-right fs-12">
<a href="{{ url('/privacy') }}" class="m-r-15 hint-text link text-black">Privacy Policy</a>
<span class="m-r-15 text-black font-montserrat">+65 345 345 5555</span>
</div>
</div>
<ul class="menu">
<li>
<a href="{{ url('/') }}">Home </a>
</li>
<li>
<a href="{{ url('/contact') }}" class="active">Contact Us</a>
</li>
</ul>
<a class="btn btn-sm btn-bordered btn-black block-title fs-12 hidden-sm hidden-xs" href="#" data-text="Sign Up">Sign Up</a>
</div>
</div>
</nav>
 
<section class="p-t-90 sm-p-t-30 p-b-30">
 

 
@yield('content')
 
</section>
 
<section class="p-b-55 p-t-75 xs-p-b-20 bg-master-darker ">
<div class="container">
<div class="row">
<div class="col-sm-4 col-xs-12 xs-m-b-40">
<img src="/assets/img/Logomakr_62Fr5N.png" width="152" height="21" data-src-retina="/assets/img/Logomakr_62Fr5N.png" class="alt" alt="">
</div>
<div class="col-sm-2 col-xs-6 xs-m-b-20">
<h6 class="font-montserrat text-uppercase fs-14 text-white p-b-10"> </h6>
<ul class="no-style">
<li class="m-b-5 no-padding"><a href="{{ url('/terms') }}" class="link text-white ">Terms Of Services</a></li>
</ul>
</div>
<div class="col-sm-2 col-xs-6 xs-m-b-20">
<h6 class="font-montserrat text-uppercase fs-14 text-white p-b-10"> </h6>
<ul class="no-style">
<li class="m-b-5 no-padding"><a href="{{ url('/privacy') }}" class="link text-white ">Privacy Policy</a></li>
</ul>
</div>
<div class="col-sm-2 col-xs-6 xs-m-b-20">
<h6 class="font-montserrat text-uppercase fs-14 text-white p-b-10"> </h6>
<ul class="no-style">
<li class="m-b-5 no-padding"><a href="{{ url('/dmca-policy') }}" class="link text-white ">DCMA Policy</a></li>
</ul>
</div>
</div>
<p class="fs-12 hint-text p-t-10 text-white">Copyright &copy; 2016. All Rights Reserved </p>
</div>
</section>
 
 
<script src="/assets/js/pace.min.js" type="text/javascript"></script>
<script type="text/javascript" src="/assets/js/pages.image.loader.js"></script>
<script type="text/javascript" src="/assets/js/jquery-1.11.1.min.js"></script>
<script type="text/javascript" src="/assets/js/bootstrap.min.js"></script>
 
<script type="text/javascript" src="/assets/js/swiper.jquery.min.js"></script>

 
<script type="text/javascript" src="/assets/js/jquery.unveil.min.js"></script>
 
 
<script type="text/javascript" src="/assets/js/pages.frontend.js"></script>
 
 
<script type="text/javascript" src="/assets/js/custom.js"></script>

</body>
</html>