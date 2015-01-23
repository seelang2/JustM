# timeslips-dev
Development and test project

This project started out as a casual Dive project with the purpose of creating an app to be created and put out in the marketplace mainly as a learning experience. To that end, I've kep the original code and some of the iterations intact for study purposes. However, as what happens with Sparks (Girl Genius reference), the desire to keep improving and developing a project set in, and thus the API component of the project has grown into a bit more than the original purpose, and I've taken the opportunity to merge a couple of other api projects into this one.

This version of the API takes the basic concepts discussed in the Dive session, along with concepts discussed from other PHP classes I've taught and adds in a proper dose of RESTful implementation. I've combined it with another project born from those prior classes and MVC framework experience I named JustM, which was a simplified MVC system that focused only on the Model portion for data services. 

This new code I've 'cleverly' dubbed JustM 2.0. The intent is to create a codebase I would consider an actual framework - a 'mostly connected' system that simply requires a little bit of custom wiring to get a working application.

JustM is meant to be the core of a simple Model-based RESTful API. APIs don't require a full-blown Model-View-Controller implementation. The Views are simply data representations, and the Controller code is minimal, especially in a RESTful implementation. The Model code, however, which contains the data access methods as well as the business logic governing the manipulation of the data, is still there. JustM provides the REST connectivity and the data access and representation as JSON (and probably JSONP). All a developer has to do to implement their own data service API is to set a few configuration parameters and create models representing their data.

I don't have any real grandiose visions about the API project. There are plenty of API tools out there. It's not like I expect JustM to become a thing of any magnitude. I'm building it mainly for my own purposes and what I believe a framework should be. Of course, it'd be pretty cool if other people find the thing useful and use it in their own projects. So I'm releasing it as open source and just putting it out there.


