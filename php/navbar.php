<!DOCTYPE html>
<!-- Coding by CodingLab | www.codinglabweb.com -->
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../png/logo.png" type="image/x-icon" />
    <!----======== CSS ======== -->
    <link rel="stylesheet" href="../css/navbar.css">

    <!----===== Boxicons CSS ===== -->
    <link href='https://unpkg.com/boxicons@2.1.1/css/boxicons.min.css' rel='stylesheet'>

    <!--<title>Dashboard Sidebar Menu</title>-->
</head>

<body>
    <nav class="sidebar close">
        <header>
            <div class="image-text">
                <a href="home.php"><span class="image">
                        <!-- <a href="home.php"> -->
                        <img src="../png/logo.png" alt="Home" style="cursor: pointer;">
                        <!-- </a> -->
                    </span></a>

                <div class="text logo-text">
                    <span class="name">4P's</span>
                    <span class="profession">Mapping Hope</span>
                </div>
            </div>

            <i class='bx bx-chevron-right toggle'></i>
        </header>

        <div class="menu-bar">
            <div class="menu">

                <ul class="menu-links">
                    <li class="nav-link">
                        <a href="home.php">
                            <i class='bx bx-trending-down icon'></i>
                            <span class="text nav-text">Dashboard</span>
                        </a>
                    </li>

                    <li class="nav-link">
                        <a href="map.php">
                            <i class='bx bx-globe icon'></i>
                            <span class="text nav-text">Map</span>
                        </a>
                    </li>

                    <li class="nav-link">
                        <a href="barangaylist.php">
                            <i class='bx bx-sitemap icon'></i>
                            <span class="text nav-text">List of Barangay</span>
                        </a>
                    </li>

                    <li class="nav-link">
                        <a href="add.php">
                            <i class='bx bx-data icon'></i>
                            <span class="text nav-text">Application Form</span>
                        </a>
                    </li>

                </ul>
            </div>

            <div class="bottom-content">
                <li class="nav-link">
                    <a href="manageuser.php">
                        <i class='bx bx-user icon'></i>
                        <span class="text nav-text">Manage Users</span>
                    </a>
                </li>
                <li class="">
                    <a href="logout.php">
                        <i class='bx bx-log-out icon'></i>
                        <span class="text nav-text">Logout</span>
                    </a>
                </li>


            </div>
        </div>

    </nav>

    <section class="home">
        <div class="text">Dashboard Sidebar</div>
    </section>

    <script>
        const body = document.querySelector('body'),
            sidebar = body.querySelector('nav'),
            toggle = body.querySelector(".toggle"),
            searchBtn = body.querySelector(".search-box"),
            modeSwitch = body.querySelector(".toggle-switch"),
            modeText = body.querySelector(".mode-text");


        searchBtn.addEventListener("click", () => {
            sidebar.classList.remove("close");
        })

        modeSwitch.addEventListener("click", () => {
            body.classList.toggle("dark");

            if (body.classList.contains("dark")) {
                modeText.innerText = "Light mode";
            } else {
                modeText.innerText = "Dark mode";

            }
        });
    </script>

</body>

</html>