
def reverseint( x: int) -> int:
    import math
    if -math.pow(2,31)< x <0:
        return -int(str(x).replace('-','')[-1::-1])
    elif x<math.pow(2,31)-1:
         return int(str(x)[-1::-1])
    else:
        return 0

print(reverseint(-15342136469))