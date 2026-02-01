<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['site']['title']; ?></title>
    <meta name="description" content="<?php echo $config['site']['meta_description'] ?? ''; ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav container">
            <a href="/" class="logo">
                <?php if (!empty($config['site']['logo'])): ?>
                    <img src="<?php echo $config['site']['logo']; ?>" alt="<?php echo $config['site']['title']; ?>">
                <?php else: ?>
                    <span><?php echo $config['site']['title']; ?></span>
                <?php endif; ?>
            </a>
            <ul class="nav-links">
                <li><a href="#services">Serviços</a></li>
                <li><a href="#about">Sobre</a></li>
                <li><a href="#gallery">Galeria</a></li>
                <li><a href="#contact">Contacto</a></li>
            </ul>
            <a href="#booking" class="btn btn-accent">Marcar Agora</a>
            <button class="mobile-menu-btn" aria-label="Menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content container">
            <h1>Bem-vindo à <span class="accent"><?php echo $config['site']['title']; ?></span></h1>
            <p>Cortes de qualidade, estilo único. Onde a tradição encontra o moderno.</p>
            <div class="hero-buttons">
                <a href="#booking" class="btn btn-accent btn-lg">Fazer Marcação</a>
                <a href="#services" class="btn btn-outline btn-lg">Ver Serviços</a>
            </div>
        </div>
        <div class="hero-bg"></div>
    </section>

    <!-- Services Section -->
    <section id="services" class="section services">
        <div class="container">
            <h2 class="section-title">Os Nossos <span class="accent">Serviços</span></h2>
            <p class="section-subtitle">Serviços de qualidade para todos os estilos</p>
            
            <div class="services-grid">
                <?php
                $services = $bookingService->getServices();
                foreach ($services as $service):
                ?>
                <div class="service-card">
                    <div class="service-icon">💈</div>
                    <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                    <p><?php echo htmlspecialchars($service['description'] ?? ''); ?></p>
                    <div class="service-meta">
                        <span class="price">€<?php echo number_format($service['price'], 2, ',', '.'); ?></span>
                        <span class="duration"><?php echo $service['duration_minutes']; ?> min</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="section about">
        <div class="container about-grid">
            <div class="about-image">
                <img src="assets/images/about.jpg" alt="Interior da barbearia">
            </div>
            <div class="about-content">
                <h2>Sobre <span class="accent">Nós</span></h2>
                <p>
                    Com anos de experiência, oferecemos serviços de barbearia de alta qualidade 
                    num ambiente acolhedor e moderno. A nossa missão é fazer com que cada cliente 
                    se sinta confiante e bem cuidado.
                </p>
                <ul class="about-features">
                    <li>✓ Profissionais experientes</li>
                    <li>✓ Produtos premium</li>
                    <li>✓ Ambiente confortável</li>
                    <li>✓ Marcações fáceis</li>
                </ul>
                <a href="#booking" class="btn btn-accent">Marcar Visita</a>
            </div>
        </div>
    </section>

    <!-- Gallery Section -->
    <section id="gallery" class="section gallery">
        <div class="container">
            <h2 class="section-title">A Nossa <span class="accent">Galeria</span></h2>
            <p class="section-subtitle">Alguns dos nossos trabalhos</p>
            
            <div class="gallery-grid">
                <div class="gallery-item"><img src="assets/images/gallery/1.jpg" alt="Trabalho 1"></div>
                <div class="gallery-item"><img src="assets/images/gallery/2.jpg" alt="Trabalho 2"></div>
                <div class="gallery-item"><img src="assets/images/gallery/3.jpg" alt="Trabalho 3"></div>
                <div class="gallery-item"><img src="assets/images/gallery/4.jpg" alt="Trabalho 4"></div>
                <div class="gallery-item"><img src="assets/images/gallery/5.jpg" alt="Trabalho 5"></div>
                <div class="gallery-item"><img src="assets/images/gallery/6.jpg" alt="Trabalho 6"></div>
            </div>
        </div>
    </section>

    <!-- Booking Section -->
    <section id="booking" class="section booking">
        <div class="container">
            <h2 class="section-title">Fazer <span class="accent">Marcação</span></h2>
            <p class="section-subtitle">Reserve o seu horário em poucos passos</p>
            
            <div class="booking-wrapper">
                <form id="booking-form" class="booking-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="booking-name">Nome</label>
                            <input type="text" id="booking-name" name="name" required placeholder="O seu nome">
                        </div>
                        <div class="form-group">
                            <label for="booking-phone">Telefone</label>
                            <input type="tel" id="booking-phone" name="phone" required placeholder="912 345 678">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="booking-service">Serviço</label>
                            <select id="booking-service" name="service_id" required>
                                <option value="">Selecione um serviço</option>
                                <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id']; ?>">
                                    <?php echo htmlspecialchars($service['name']); ?> - €<?php echo number_format($service['price'], 2, ',', '.'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="booking-staff">Profissional</label>
                            <select id="booking-staff" name="staff_id">
                                <option value="">Sem preferência</option>
                                <?php 
                                $staff = $bookingService->getStaff();
                                foreach ($staff as $member): 
                                ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="booking-date">Data</label>
                            <input type="date" id="booking-date" name="date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="booking-time">Hora</label>
                            <select id="booking-time" name="time" required disabled>
                                <option value="">Selecione primeiro a data</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="booking-notes">Notas (opcional)</label>
                        <textarea id="booking-notes" name="notes" rows="3" placeholder="Alguma informação adicional?"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-accent btn-block">
                        Confirmar Marcação
                    </button>
                </form>
                
                <div id="booking-success" class="booking-success" style="display: none;">
                    <div class="success-icon">✓</div>
                    <h3>Marcação Confirmada!</h3>
                    <p>Receberás uma confirmação por SMS.</p>
                    <button onclick="location.reload()" class="btn btn-outline">Nova Marcação</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="section contact">
        <div class="container contact-grid">
            <div class="contact-info">
                <h2>Contacte-<span class="accent">nos</span></h2>
                <p>Estamos disponíveis para responder às suas questões.</p>
                
                <div class="contact-items">
                    <div class="contact-item">
                        <span class="contact-icon">📍</span>
                        <div>
                            <strong>Morada</strong>
                            <p><?php echo $config['site']['address'] ?? 'A definir'; ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <span class="contact-icon">📞</span>
                        <div>
                            <strong>Telefone</strong>
                            <p><?php echo $config['site']['phone'] ?? 'A definir'; ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <span class="contact-icon">✉️</span>
                        <div>
                            <strong>Email</strong>
                            <p><?php echo $config['site']['email'] ?? 'A definir'; ?></p>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($config['site']['social'])): ?>
                <div class="social-links">
                    <?php if (!empty($config['site']['social']['instagram'])): ?>
                    <a href="<?php echo $config['site']['social']['instagram']; ?>" target="_blank" aria-label="Instagram">
                        <svg viewBox="0 0 24 24"><path d="M12 2c2.717 0 3.056.01 4.122.06 1.065.05 1.79.217 2.428.465.66.254 1.216.598 1.772 1.153a4.908 4.908 0 0 1 1.153 1.772c.247.637.415 1.363.465 2.428.047 1.066.06 1.405.06 4.122 0 2.717-.01 3.056-.06 4.122-.05 1.065-.218 1.79-.465 2.428a4.883 4.883 0 0 1-1.153 1.772 4.915 4.915 0 0 1-1.772 1.153c-.637.247-1.363.415-2.428.465-1.066.047-1.405.06-4.122.06-2.717 0-3.056-.01-4.122-.06-1.065-.05-1.79-.218-2.428-.465a4.89 4.89 0 0 1-1.772-1.153 4.904 4.904 0 0 1-1.153-1.772c-.248-.637-.415-1.363-.465-2.428C2.013 15.056 2 14.717 2 12c0-2.717.01-3.056.06-4.122.05-1.066.217-1.79.465-2.428a4.88 4.88 0 0 1 1.153-1.772A4.897 4.897 0 0 1 5.45 2.525c.638-.248 1.362-.415 2.428-.465C8.944 2.013 9.283 2 12 2zm0 5a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm6.5-.25a1.25 1.25 0 0 0-2.5 0 1.25 1.25 0 0 0 2.5 0zM12 9a3 3 0 1 1 0 6 3 3 0 0 1 0-6z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($config['site']['social']['facebook'])): ?>
                    <a href="<?php echo $config['site']['social']['facebook']; ?>" target="_blank" aria-label="Facebook">
                        <svg viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="contact-map">
                <?php if (!empty($config['site']['google_maps_embed'])): ?>
                <iframe src="<?php echo $config['site']['google_maps_embed']; ?>" allowfullscreen="" loading="lazy"></iframe>
                <?php else: ?>
                <div class="map-placeholder">
                    <p>Mapa em breve</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <span class="logo"><?php echo $config['site']['title']; ?></span>
                    <p>Qualidade e estilo desde sempre.</p>
                </div>
                <div class="footer-links">
                    <a href="#services">Serviços</a>
                    <a href="#about">Sobre</a>
                    <a href="#contact">Contacto</a>
                    <a href="<?php echo $config['admin']['path']; ?>">Admin</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo $config['site']['title']; ?>. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Bot Widget -->
    <?php if (!empty($config['modules']['bot_widget']['enabled'])): ?>
    <script 
        src="<?php echo dirname($_SERVER['PHP_SELF']); ?>/../modules/bot-widget/widget.js"
        data-api="<?php echo dirname($_SERVER['PHP_SELF']); ?>/../modules/bot-widget/handler.php"
        data-name="<?php echo $config['modules']['bot_widget']['name'] ?? 'Assistente'; ?>"
        data-theme="<?php echo $config['modules']['bot_widget']['theme'] ?? 'dark'; ?>"
        data-position="<?php echo $config['modules']['bot_widget']['position'] ?? 'bottom-right'; ?>"
        data-welcome="<?php echo $config['modules']['bot_widget']['welcome_message'] ?? 'Olá! Como posso ajudar?'; ?>"
    ></script>
    <?php endif; ?>

    <script src="assets/js/app.js"></script>
    
    <?php if (!empty($config['site']['analytics']['google'])): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $config['site']['analytics']['google']; ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?php echo $config['site']['analytics']['google']; ?>');
    </script>
    <?php endif; ?>
</body>
</html>
