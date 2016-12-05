#Facebook page Component

Komponenta pro získávání feedu z facebook stránky

- Instalace

    Přidat tento repozitář do composer.json
- Spuštení - presenter

    Do Presenteru přidat kód pro vytvoření komponenty
```
    /**
     * @param $name
     * @return FacebookPageComponent\FacebookPage
     */
    protected function createComponentFacebookPage($name)
    {
        $fbPage = new FacebookPageComponent\FacebookPage($this->_translator, $this, $name);
        $fbPage->setPageId(1554457238147502);
        
        //Optional
        $fbPage->setGraphApiVersion("v2.8");
        $fbPage->setPostLimit(2);

        return $fbPage;
    }


```
- Spuštění šablona
    
    Do šablony přidat
    ```latte
    {control facebookPage}
    ```