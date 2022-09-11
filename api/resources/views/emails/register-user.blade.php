<!DOCTYPE html>
<html>
<body>

<h1>Hi {{$name}}</h1>

<form action="{{$url}}/confirm-register" method="POST">
  <input type="hidden" id="fname" name="name" value="{{$name}}"><br><br>
  <input type="hidden" id="custId" name="email" value="{{$email}}">
  <input type="hidden" id="custId" name="password" value="{{$password}}">
  <input type="submit" style=" background-color: #0095ff;
  border: 1px solid transparent;
  border-radius: 3px;
  box-shadow: rgba(255, 255, 255, .4) 0 1px 0 0 inset;
  box-sizing: border-box;
  color: #fff;
  cursor: pointer;
  display: inline-block;
  font-size: 13px;
  font-weight: 400;
  line-height: 1.15385;
  margin: 0;
  outline: none;
  padding: 8px .8em;
  position: relative;
  text-align: center;
  text-decoration: none;
  user-select: none;
  -webkit-user-select: none;
  touch-action: manipulation;
  vertical-align: baseline;
  
  white-space: nowrap;" value="Submit" >
</form>


</body>
</html>
